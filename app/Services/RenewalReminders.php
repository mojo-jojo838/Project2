<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\SmsReminder;
use Illuminate\Support\Facades\Http;
use Throwable;

class RenewalReminders
{
    public function queueDue(): int
    {
        $target = today()->addDays(3);
        $created = 0;
        $memberships = Membership::with(['user', 'plan'])
            ->whereBetween('expires_at', [today(), $target])->whereDate('starts_at', '<=', today())->get();
        foreach ($memberships as $membership) {
            $member = $membership->user;
            if (! $member || $member->trashed() || $member->approval_status !== 'approved'
                || ! $member->sms_consent || ! $member->phone) continue;
            $renewed = Membership::where('user_id', $member->id)
                ->whereDate('starts_at', '>', $membership->expires_at)->exists();
            if ($renewed) continue;

            $reminder = SmsReminder::firstOrCreate(
                ['membership_id' => $membership->id],
                [
                    'user_id' => $member->id,
                    'phone' => $member->phone,
                    'scheduled_for' => today(),
                    'status' => 'queued',
                    'message' => "D'fitness Gym: Your {$membership->plan->name} membership expires on {$membership->expires_at->format('M j, Y')}. Please visit the gym to renew.",
                ]
            );
            if ($reminder->wasRecentlyCreated) $created++;
        }
        return $created;
    }

    public function configured(): bool
    {
        return filled(config('services.twilio.account_sid'))
            && filled(config('services.twilio.auth_token'))
            && filled(config('services.twilio.from'));
    }

    public function sendQueued(): array
    {
        if (! $this->configured()) return ['submitted' => 0, 'failed' => 0, 'configured' => false];
        $submitted = 0;
        $failed = 0;
        SmsReminder::with(['user', 'membership'])->where('status', 'queued')
            ->whereDate('scheduled_for', '<=', today())->orderBy('id')->chunkById(50, function ($reminders) use (&$submitted, &$failed) {
                foreach ($reminders as $reminder) {
                    $member = $reminder->user;
                    if (! $member || $member->trashed() || ! $member->sms_consent || ! $member->phone) {
                        $reminder->update(['status' => 'cancelled', 'error' => 'Member unavailable or SMS consent withdrawn.']);
                        continue;
                    }
                    $renewed = Membership::where('user_id', $member->id)
                        ->whereDate('starts_at', '>', $reminder->membership->expires_at)->exists();
                    if ($renewed || $reminder->membership->expires_at->isBefore(today())) {
                        $reminder->update(['status' => 'cancelled', 'error' => 'Membership renewed or already expired.']);
                        continue;
                    }
                    try {
                        $sid = config('services.twilio.account_sid');
                        $response = Http::asForm()
                            ->withBasicAuth($sid, config('services.twilio.auth_token'))
                            ->timeout(15)
                            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                                'To' => $member->phone,
                                'From' => config('services.twilio.from'),
                                'Body' => $reminder->message,
                            ]);
                        if ($response->successful() && $response->json('sid')) {
                            $reminder->update([
                                'phone' => $member->phone,
                                'status' => 'submitted',
                                'provider_sid' => $response->json('sid'),
                                'submitted_at' => now(),
                                'error' => null,
                            ]);
                            $submitted++;
                        } else {
                            $reminder->update(['status' => 'failed', 'error' => 'SMS provider rejected the request (HTTP '.$response->status().').']);
                            $failed++;
                        }
                    } catch (Throwable $exception) {
                        $reminder->update(['status' => 'failed', 'error' => 'SMS provider connection failed.']);
                        $failed++;
                    }
                }
            });
        return ['submitted' => $submitted, 'failed' => $failed, 'configured' => true];
    }
}
