<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('payments')->where('status', 'due')->orderBy('id')->chunkById(100, function ($payments) {
            foreach ($payments as $payment) {
                DB::table('payments')->where('id', $payment->id)->update([
                    'status' => 'paid',
                    'paid_at' => \Illuminate\Support\Carbon::parse($payment->created_at)->toDateString(),
                    'method' => 'automatic',
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Historical payments cannot be distinguished from automatically received payments after migration.
    }
};
