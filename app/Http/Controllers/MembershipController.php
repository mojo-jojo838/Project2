<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Models\SmsReminder;
use App\Services\RenewalReminders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MembershipController extends Controller
{
    private function requireAdmin(Request $request): void
    {
        abort_unless($request->user()->role === 'admin', 403);
    }

    public function index(Request $request, RenewalReminders $reminderService): View
    {
        $this->requireAdmin($request);
        return view('memberships.index', [
            'plans' => MembershipPlan::whereIn('name', ['Student', 'Regular'])->orderBy('name')->get(),
            'members' => User::where('role', 'member')->where('approval_status', 'approved')->orderBy('name')->get(),
            'memberships' => Membership::with(['user', 'plan'])->withCount('payments')->orderByDesc('expires_at')->paginate(15),
            'reminders' => SmsReminder::with(['user', 'membership.plan'])->latest()->paginate(10, ['*'], 'reminderPage'),
            'queuedCount' => SmsReminder::where('status', 'queued')->count(),
            'submittedCount' => SmsReminder::where('status', 'submitted')->count(),
            'failedCount' => SmsReminder::where('status', 'failed')->count(),
            'gatewayConfigured' => $reminderService->configured(),
        ]);
    }

    public function storeMembership(Request $request): RedirectResponse
    {
        $this->requireAdmin($request);
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'membership_plan_id' => ['required', 'exists:membership_plans,id'],
            'starts_at' => ['required', 'date'],
        ]);
        $plan = MembershipPlan::findOrFail($data['membership_plan_id']);
        abort_unless(in_array($plan->name, ['Student', 'Regular'], true), 422);
        $start = \Illuminate\Support\Carbon::parse($data['starts_at'])->startOfDay();
        $end = $start->copy()->addDays($plan->duration_days - 1);
        $isRenewal = DB::transaction(function () use ($data, $plan, $start, $end) {
            $member = User::whereKey($data['user_id'])->lockForUpdate()->firstOrFail();
            abort_unless($member->role === 'member' && $member->approval_status === 'approved', 422);
            $overlap = Membership::where('user_id', $member->id)
                ->whereDate('starts_at', '<=', $end->toDateString())
                ->whereDate('expires_at', '>=', $start->toDateString())->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['starts_at' => 'This member already has a membership during those dates.']);
            }
            $isRenewal = Membership::where('user_id', $member->id)->exists();
            $membership = Membership::create([
                'user_id' => $member->id,
                'membership_plan_id' => $plan->id,
                'starts_at' => $start,
                'expires_at' => $end,
            ]);
            Payment::create([
                'user_id' => $member->id,
                'membership_id' => $membership->id,
                'amount' => $plan->price,
                'status' => 'paid',
                'paid_at' => today(),
                'method' => 'automatic',
            ]);
            return $isRenewal;
        });
        return back()->with('status', ($isRenewal ? 'Renewal' : 'New membership').' saved. ₱'.number_format($plan->price, 2).' payment recorded automatically.');
    }

    public function destroyExpired(Request $request, Membership $membership): RedirectResponse
    {
        $this->requireAdmin($request);
        abort_unless($membership->status === 'Expired', 422);
        if ($membership->payments()->exists()) {
            return back()->withErrors(['membership' => 'Memberships with payment records cannot be deleted.']);
        }
        $membership->delete();
        return back()->with('status', 'Expired membership deleted.');
    }
}
