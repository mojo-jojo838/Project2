<?php

namespace App\Http\Controllers;

use App\Services\RenewalReminders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    private function requireAdmin(Request $request): void
    {
        abort_unless($request->user()->role === 'admin', 403);
    }

    public function index(Request $request): RedirectResponse
    {
        $this->requireAdmin($request);
        return redirect(route('admin.memberships.index').'#renewal-reminders');
    }

    public function queueNow(Request $request, RenewalReminders $service): RedirectResponse
    {
        $this->requireAdmin($request);
        $count = $service->queueDue();
        if (! $service->configured()) {
            return redirect(route('admin.memberships.index').'#renewal-reminders')
                ->with('status', $count.' new reminder(s) queued. SMS was not sent because the Twilio settings are not configured.');
        }
        $result = $service->sendQueued();
        return redirect(route('admin.memberships.index').'#renewal-reminders')
            ->with('status', $count.' new reminder(s) queued. '.$result['submitted'].' submitted to the SMS provider; '.$result['failed'].' failed.');
    }
}
