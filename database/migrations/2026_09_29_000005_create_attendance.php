<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('qr_token', 64)->nullable()->unique();
        });
        foreach (DB::table('users')->where('role', 'member')->where('approval_status', 'approved')->pluck('id') as $id) {
            DB::table('users')->where('id', $id)->update(['qr_token' => Str::random(48)]);
        }
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('walk_in_name')->nullable();
            $table->string('method', 20);
            $table->date('check_in_date');
            $table->timestamp('checked_in_at');
            $table->timestamps();
            $table->unique(['user_id', 'check_in_date']);
            $table->index('checked_in_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('qr_token'));
    }
};
