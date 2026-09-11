<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dropbox_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('client_id')->unique();
            $table->string('client_secret')->unique();
            $table->text('access_token')->nullable();
            $table->text('refresh_token');
            $table->timestamp('token_expires_at')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null')->onUpdate('set null');
            $table->bigInteger('remaining_storage')->default(2147483648); // 2GB = 2 * 1024^3 bytes
            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dropbox_accounts');
    }
};
