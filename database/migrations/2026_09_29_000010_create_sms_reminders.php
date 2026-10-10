<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 13)->nullable();
            $table->boolean('sms_consent')->default(false);
            $table->timestamp('sms_consented_at')->nullable();
        });
        Schema::create('sms_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('membership_id')->unique()->constrained()->restrictOnDelete();
            $table->string('phone', 13);
            $table->text('message');
            $table->date('scheduled_for');
            $table->string('status', 20)->default('queued');
            $table->string('provider_sid')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_reminders');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['phone', 'sms_consent', 'sms_consented_at']));
    }
};
