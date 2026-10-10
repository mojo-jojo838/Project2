<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\RenewalReminders;
use App\Services\AutomaticCheckout;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('reminders:queue', function (RenewalReminders $reminders) {
    $this->info($reminders->queueDue().' renewal reminder(s) queued.');
})->purpose('Queue consented membership renewal reminders three days before expiry');

Artisan::command('reminders:send', function (RenewalReminders $reminders) {
    $result = $reminders->sendQueued();
    if (! $result['configured']) {
        $this->warn('SMS gateway is not configured; queued reminders were not sent.');
        return;
    }
    $this->info($result['submitted'].' submitted, '.$result['failed'].' failed.');
})->purpose('Submit queued renewal SMS messages to the configured gateway');

Schedule::command('reminders:queue')->dailyAt('09:00')->withoutOverlapping();
Schedule::command('reminders:send')->dailyAt('09:05')->withoutOverlapping();

Artisan::command('attendance:close-overdue', function (AutomaticCheckout $checkout) {
    $this->info($checkout->closeOverdue().' member visit(s) checked out automatically.');
})->purpose('Close open member visits at 10:00 PM');

Schedule::command('attendance:close-overdue')->everyMinute()->withoutOverlapping();
