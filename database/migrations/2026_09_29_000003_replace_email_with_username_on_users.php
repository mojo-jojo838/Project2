<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->unique();
        });

        foreach (DB::table('users')->select('id', 'email', 'role')->get() as $user) {
            $base = $user->role === 'admin' ? 'admin' : 'member';
            $username = $base === 'admin' ? 'admin' : 'member'.$user->id;
            if (DB::table('users')->where('username', $username)->exists()) {
                $username .= $user->id;
            }
            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->dropColumn(['email', 'email_verified_at']);
        });
        Schema::dropIfExists('password_reset_tokens');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
        });
        DB::table('users')->orderBy('id')->get()->each(function ($user) {
            DB::table('users')->where('id', $user->id)->update(['email' => $user->username.'@example.invalid']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_username_unique');
            $table->dropColumn('username');
        });
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
};
