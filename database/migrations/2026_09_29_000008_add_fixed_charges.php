<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreignId('membership_id')->nullable()->change();
            $table->date('paid_at')->nullable()->change();
            $table->string('method', 20)->nullable()->change();
            $table->foreignId('attendance_id')->nullable()->unique()->constrained('attendance')->restrictOnDelete();
            $table->string('status', 20)->default('paid');
        });

        foreach (['Student' => '900.00', 'Regular' => '950.00'] as $name => $price) {
            $existing = DB::table('membership_plans')->where('name', $name)->first();
            if ($existing) {
                DB::table('membership_plans')->where('id', $existing->id)
                    ->update(['duration_days' => 30, 'price' => $price, 'updated_at' => now()]);
            } else {
                DB::table('membership_plans')->insert([
                    'name' => $name, 'duration_days' => 30, 'price' => $price,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['attendance_id']);
            $table->dropUnique(['attendance_id']);
            $table->dropColumn(['attendance_id', 'status']);
            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreignId('membership_id')->nullable(false)->change();
            $table->date('paid_at')->nullable(false)->change();
            $table->string('method', 20)->nullable(false)->change();
        });
    }
};
