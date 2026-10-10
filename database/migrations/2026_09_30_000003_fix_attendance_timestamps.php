<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dateTime('checked_in_at')->change();
            $table->dateTime('checked_out_at')->nullable()->change();
        });

        DB::table('attendance')->whereNotNull('user_id')->whereNotNull('checked_out_at')
            ->whereColumn('checked_in_at', 'checked_out_at')
            ->whereColumn('created_at', '<', 'checked_in_at')
            ->update(['checked_in_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->timestamp('checked_in_at')->change();
            $table->timestamp('checked_out_at')->nullable()->change();
        });
    }
};
