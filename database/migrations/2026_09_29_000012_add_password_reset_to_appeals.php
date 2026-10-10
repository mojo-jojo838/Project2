<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appeals', function (Blueprint $table) {
            $table->string('reset_code_hash')->nullable();
            $table->timestamp('reset_expires_at')->nullable();
            $table->timestamp('reset_used_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('appeals', fn (Blueprint $table) => $table->dropColumn(['reset_code_hash', 'reset_expires_at', 'reset_used_at']));
    }
};
