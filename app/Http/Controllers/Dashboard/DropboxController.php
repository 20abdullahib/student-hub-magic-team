<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DropboxAccount;
use App\Models\File;
use App\Models\Subject;
use App\Services\DropboxService;
use Illuminate\Http\Request;

class DropboxController extends Controller
{
    protected $dropboxService;

    public function __construct(DropboxService $dropboxService)
    {
        $this->dropboxService = $dropboxService;
    }

    // Account Management
    public function listAccounts(Request $request)
    {
        $totalSpace = 2147483648; // Total storage space in bytes

        // Query accounts with optional filters
        $accounts = DropboxAccount::select('id', 'email', 'client_id', 'department_id', 'remaining_storage')
            ->when($request->email, function ($query, $email) {
                return $query->where('email', 'like', "%$email%");
            })
            ->when($request->department_id, function ($query, $departmentId) {
                return $query->where('department_id', $departmentId);
            })
            ->paginate(25); // Paginate with 25 accounts per page

        // Add department name and remaining percentage to each account
        $accounts->each(function ($account) use ($totalSpace) {
            $account->department_name = Department::find($account->department_id)->name;
            $account->remaining_percentage = ($account->remaining_storage / $totalSpace) * 100;
        });

        // Get all departments for the filter dropdown
        $departments = Department::all();

        return view('dashboard.pages.dropbox.accounts', compact('accounts', 'totalSpace', 'departments'));
    }

    public function showForm()
    {
        return view('dashboard.pages.dropbox.AddNewAccount', [
            'departments' => Department::all(),
        ]);
    }

    public function setupAccount(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'client_id' => 'required|string',
            'client_secret' => 'required|string',
            'refresh_token' => 'required|string',
            'department_id' => 'required|exists:departments,id',
        ]);

        // Exchanges the refresh token for a fresh access token and returns the
        // payload so it can be stored right away — access_token/token_expires_at
        // must never stay NULL after a successful setup.
        $tokenData = $this->dropboxService->verifyCredentials($validated);

        if ($tokenData === null) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Invalid Dropbox credentials');
        }

        DropboxAccount::updateOrCreate(
            ['email' => $validated['email']],
            array_merge($validated, [
                'access_token' => $tokenData['access_token'],
                'token_expires_at' => now()->addSeconds($tokenData['expires_in']),
            ])
        );

        return redirect()->back()->with('success', 'Account setup successful');
    }

    public function deleteAccount($id)
    {
        $account = DropboxAccount::findOrFail($id);
        $account->delete();

        return redirect()->route('dropbox.account.index')->with('success', 'Account deleted successfully');
    }

    public function updateDropbox(Request $request)
    {
        DropboxAccount::findOrFail($request->account_id)
            ->update(['remaining_storage' => $request->remaining_storage]);

        return response()->json(['message' => 'Storage updated']);
    }

    // File Management
    public function showUploadForm()
    {
        $admin = auth('admin')->user();
        $departments = Department::all();
        $subjects = Subject::all();

        return view('dashboard.pages.dropbox.UploadFiles', compact('admin', 'departments', 'subjects'));
    }

    public function storeFileDetails(Request $request)
    {
        File::create($request->validate([
            'name' => 'required|string',
            'path' => 'required|string',
            'size' => 'required|integer',
            'subject_id' => 'required|exists:subjects,id',
            'dropbox_account_id' => 'required|exists:dropbox_accounts,id',
            'link' => 'required|string',
            'file_id' => 'required|string',
            'rlkey' => 'required|string',
        ]));

        return response()->json(['message' => 'File details saved']);
    }

    public function listFiles(Request $request)
    {
        $files = File::with(['subject', 'dropboxAccount.department'])
            ->when($request->file_name, function ($query, $fileName) {
                return $query->where('name', 'like', "%$fileName%");
            })
            ->when($request->subject_name, function ($query, $subjectName) {
                return $query->whereHas('subject', function ($q) use ($subjectName) {
                    $q->where('name', 'like', "%$subjectName%");
                });
            })
            ->when($request->department_name, function ($query, $departmentName) {
                return $query->whereHas('dropboxAccount.department', function ($q) use ($departmentName) {
                    $q->where('name', 'like', "%$departmentName%");
                });
            })
            ->paginate(25);

        return view('dashboard.pages.dropbox.files', [
            'files' => $files,
            'subjects' => Subject::all(),
            'departments' => Department::all(),
        ]);
    }

    public function deleteFiles(File $file)
    {
        $file->delete();

        return response()->json(['success' => true]);
    }

    public function getAccountForUpload(Request $request)
    {
        $subject = Subject::with('department')->findOrFail($request->subject_id);

        $accounts = DropboxAccount::where('department_id', $subject->department_id)
            ->get()
            ->map(function ($account) {
                $this->dropboxService->ensureValidToken($account);

                return [
                    'id' => $account->id,
                    'email' => $account->email,
                    'department_id' => $account->department_id,
                    'department_name' => $subject->department->name ?? null,
                ];
            });

        return response()->json($accounts);
    }

    public function showFiles($departmentId)
    {
        $department = Department::with('dropboxAccounts')->findOrFail($departmentId);

        $fileLinks = $department->dropboxAccounts->flatMap(function ($account) {
            $this->dropboxService->ensureValidToken($account);

            return $this->dropboxService->getAccountFiles($account);
        });

        return view('website.pages.resource.resources', ['fileLinks' => $fileLinks]);
    }

    // Token Management
    public function refreshAllTokens()
    {
        DropboxAccount::all()->each(function ($account) {
            $this->dropboxService->refreshAccessToken($account);
        });

        return response()->json(['message' => 'Tokens refreshed successfully']);
    }

    public function getAccessToken(Request $request)
    {
        $account = DropboxAccount::findOrFail($request->account_id);

        // Validate the token live; the stored token can be dead even when
        // token_expires_at is still in the future (silent refresh failure,
        // revoked session, regenerated refresh token, ...).
        if ($this->dropboxService->getValidToken($account)) {
            return response()->json(['access_token' => $account->access_token]);
        }

        // Do not hand out a broken token — tell the JS exactly which account is broken.
        return response()->json([
            'error' => "Dropbox account {$account->email} could not provide a valid access token. Re-link it in Dashboard → Dropbox accounts (the refresh token may have been revoked).",
            'account_id' => $account->id,
        ], 502);
    }
}
