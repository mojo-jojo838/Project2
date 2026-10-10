<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendance', fn (Blueprint $table) => $table->boolean('auto_checked_out')->default(false));
    }

    public function down(): void
    {
        Schema::table('attendance', fn (Blueprint $table) => $table->dropColumn('auto_checked_out'));
    }
};
