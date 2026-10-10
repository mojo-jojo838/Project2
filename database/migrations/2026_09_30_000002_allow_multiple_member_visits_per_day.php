<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->index(['user_id', 'checked_out_at', 'checked_in_at'], 'attendance_member_open_visit_index');
        });
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropUnique('attendance_user_id_check_in_date_unique');
        });
    }

    public function down(): void
    {
        $hasMultipleVisits = DB::table('attendance')->whereNotNull('user_id')
            ->select('user_id', 'check_in_date')->groupBy('user_id', 'check_in_date')
            ->havingRaw('COUNT(*) > 1')->exists();
        if ($hasMultipleVisits) {
            throw new RuntimeException('Cannot restore the one-visit-per-day constraint while multiple visits exist.');
        }

        Schema::table('attendance', function (Blueprint $table) {
            $table->unique(['user_id', 'check_in_date']);
        });
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropIndex('attendance_member_open_visit_index');
        });
    }
};
