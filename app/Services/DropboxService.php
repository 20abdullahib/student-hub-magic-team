<?php

namespace App\Services;

use App\Models\DropboxAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DropboxService
{
    /**
     * Exchange a refresh token for a fresh access token.
     *
     * @return array|null ['access_token' => string, 'expires_in' => int] or null on failure.
     */
    public function verifyCredentials(array $credentials): ?array
    {
        try {
            $clientId = trim($credentials['client_id']);
            $clientSecret = trim($credentials['client_secret']);
            $refreshToken = trim($credentials['refresh_token']);

            $response = Http::withoutVerifying() // <--- Disables cURL SSL certificate check
                ->asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->post('https://api.dropbox.com/oauth2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ]);

            if (! $response->successful() || empty($response->json('access_token'))) {
                Log::error('Dropbox OAuth verification response error: '.$response->body());

                return null;
            }

            // Return the token payload so the caller can persist it immediately
            // (access_token must never be left NULL after a successful setup).
            return [
                'access_token' => $response->json('access_token'),
                'expires_in' => (int) $response->json('expires_in'),
            ];
        } catch (\Exception $e) {
            Log::error('Dropbox verification failed: '.$e->getMessage());

            return null;
        }
    }

    public function refreshAccessToken(DropboxAccount $account): bool
    {
        try {
            // Same SSL-tolerant client as verifyCredentials(): a failing local
            // CA bundle must never silently break token refreshes.
            $response = Http::withoutVerifying()
                ->asForm()
                ->withBasicAuth($account->client_id, $account->client_secret)
                ->post('https://api.dropbox.com/oauth2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $account->refresh_token,
                ]);

            if ($response->successful()) {
                $account->update([
                    'access_token' => $response->json('access_token'),
                    'token_expires_at' => now()->addSeconds($response->json('expires_in')),
                ]);

                return true;
            }

            // e.g. invalid_grant → the refresh token was revoked/regenerated: the account must be re-linked.
            Log::error("Failed to refresh token for account {$account->id} ({$account->email}): ".$response->body());

            return false;
        } catch (\Exception $e) {
            Log::error("Token refresh failed for account {$account->id} ({$account->email}): ".$e->getMessage());

            return false;
        }
    }

    public function ensureValidToken(DropboxAccount $account): void
    {
        if ($this->needsTokenRefresh($account)) {
            $this->refreshAccessToken($account);
            // Refresh account instance from database to get the new access_token
            $account->refresh();
        }
    }

    /**
     * Live-check the stored access token against Dropbox (cheap endpoint).
     * The DB token_expires_at alone is not enough: a token can be dead while
     * its expiry timestamp is still in the future (e.g. a silent refresh failure).
     */
    public function validateToken(DropboxAccount $account): bool
    {
        try {
            // Same SSL-tolerant client as verifyCredentials().
            // NOTE: Dropbox RPC endpoints reject an empty JSON array body —
            // an explicit '{}' object must be sent instead of post($url, []).
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer '.$account->access_token,
                ])->withBody('{}', 'application/json')
                ->post('https://api.dropboxapi.com/2/users/get_current_account');

            return $response->successful();
        } catch (\Exception $e) {
            Log::error("Token validation failed for account {$account->id} ({$account->email}): ".$e->getMessage());

            return false;
        }
    }

    /**
     * Guarantee a working access token: validate live, refresh once if dead,
     * and report failure instead of silently handing out a broken token.
     *
     * @return bool true when the account has a valid access token.
     */
    public function getValidToken(DropboxAccount $account): bool
    {
        if ($this->validateToken($account)) {
            return true;
        }

        // Token is dead → force a refresh and validate the new token.
        if ($this->refreshAccessToken($account) && $this->validateToken($account)) {
            return true;
        }

        return false;
    }

    private function needsTokenRefresh(DropboxAccount $account): bool
    {
        // 1. Check database expiration timestamp first (avoids an unnecessary HTTP call)
        if ($account->token_expires_at && now()->addMinutes(2)->gte($account->token_expires_at)) {
            return true;
        }

        // 2. Fallback check against Dropbox check endpoint
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$account->access_token,
                'Content-Type' => 'application/json',
            ])->post('https://api.dropboxapi.com/2/check/user', [
                'query' => 'ping',
            ]);

            return ! $response->successful();
        } catch (\Exception $e) {
            Log::error('Token check failed: '.$e->getMessage());

            return true;
        }
    }

    public function getAccountFiles(DropboxAccount $account): array
    {
        $this->ensureValidToken($account);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$account->access_token,
                'Content-Type' => 'application/json',
            ])->post('https://api.dropboxapi.com/2/files/list_folder', [
                'path' => '',
            ]);

            return $response->successful()
                ? $this->formatFiles($response->json()['entries'] ?? [], $account)
                : [];
        } catch (\Exception $e) {
            Log::error('File listing failed: '.$e->getMessage());

            return [];
        }
    }

    private function formatFiles(array $files, DropboxAccount $account): array
    {
        return collect($files)->filter(function ($file) {
            return ($file['.tag'] ?? '') === 'file';
        })->map(function ($file) use ($account) {
            return [
                'name' => $file['name'],
                'path' => $file['path_lower'],
                'link' => $this->getTemporaryLink($account, $file['path_lower']),
            ];
        })->values()->toArray();
    }

    private function getTemporaryLink(DropboxAccount $account, string $filePath): string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$account->access_token,
                'Content-Type' => 'application/json',
            ])->post('https://api.dropboxapi.com/2/files/get_temporary_link', [
                'path' => $filePath,
            ]);

            return $response->successful() ? $response->json()['link'] : '';
        } catch (\Exception $e) {
            Log::error('Temporary link failed: '.$e->getMessage());

            return '';
        }
    }
}
