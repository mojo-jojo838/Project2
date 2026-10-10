@extends('layouts.app')
@section('title', 'Memberships')
@section('content')
<section class="dashboard">
    <div class="dashboard-head"><div><p class="kicker">ADMIN WORKSPACE</p><h1>Gym memberships.</h1><p>Assign a new membership or schedule a renewal.</p></div></div>
    @if(session('status')) <div class="notice success" role="status">{{ session('status') }}</div> @endif
    @if($errors->any()) <div class="notice error" role="alert">{{ $errors->first() }}</div> @endif
    <div class="management-grid membership-management-grid">
        <section class="management-card"><p class="kicker">NEW OR RENEWAL</p><h2>Assign a membership</h2><form method="POST" action="{{ route('admin.memberships.store') }}">@csrf
            <label for="user-id">Approved member</label><select id="user-id" name="user_id" required><option value="">Select member</option>@foreach($members as $member)<option value="{{ $member->id }}" @selected(old('user_id') == $member->id)>{{ $member->name }} ({{ $member->username }})</option>@endforeach</select>
            <label for="plan-id">Membership plan</label><select id="plan-id" name="membership_plan_id" required><option value="">Select plan</option>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected(old('membership_plan_id') == $plan->id)>{{ $plan->name }} · {{ $plan->duration_days }} days · ₱{{ number_format($plan->price, 2) }}</option>@endforeach</select>
            <label for="starts-at">Start date</label><input id="starts-at" type="date" name="starts_at" required value="{{ old('starts_at', now()->toDateString()) }}">
            <button class="primary-button" type="submit">Save membership and payment</button>
        </form><p class="fine-print">For renewals, choose a start date after the current membership expires.</p></section>
        <section class="management-card membership-records-card"><p class="kicker">MEMBERSHIP HISTORY</p><h2>Current records</h2>
        <div class="table-wrap"><table><thead><tr><th>Member</th><th>Plan</th><th>Dates</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse($memberships as $membership)
        <tr><td><strong>{{ $membership->user->name }}</strong><small>{{ '@'.$membership->user->username }}</small></td><td>{{ $membership->plan->name }}</td><td>{{ $membership->starts_at->format('M j, Y') }} – {{ $membership->expires_at->format('M j, Y') }}</td><td><span class="status-pill {{ strtolower($membership->status) }}">{{ $membership->status }}</span></td><td>@if($membership->status === 'Expired' && $membership->payments_count === 0)<form method="POST" action="{{ route('admin.memberships.destroy', $membership) }}" onsubmit="return confirm('Delete this expired membership record?')">@csrf @method('DELETE')<button class="text-button" type="submit">Delete</button></form>@endif</td></tr>
        @empty <tr><td colspan="5">No memberships yet. Assign a plan to an approved member.</td></tr> @endforelse
        </tbody></table></div><div class="pagination">{{ $memberships->links() }}</div></section>
    </div>
    <section id="renewal-reminders" class="reminder-section">
        <h2>Renewal reminders</h2><p class="card-help">Reminders are prepared during the three days before a membership expires for members who opted in to SMS.</p>
        <div class="stats"><article><span>QUEUED</span><strong>{{ $queuedCount }}</strong></article><article><span>SUBMITTED TO SMS PROVIDER</span><strong>{{ $submittedCount }}</strong></article></div>
        @if(!$gatewayConfigured)<div class="notice error" role="status">SMS gateway credentials are not configured. Reminders can be queued but will not be sent.</div>@endif
        <div class="reminder-toolbar"><p>{{ $failedCount }} failed reminder(s). Only members with SMS consent and a mobile number are eligible.</p><form method="POST" action="{{ route('admin.reminders.queue') }}">@csrf<button class="secondary-button" type="submit">Queue and send reminders now</button></form></div>
        <h2>Reminder history</h2><div class="table-wrap"><table><thead><tr><th>Member</th><th>Membership</th><th>Phone</th><th>Scheduled</th><th>Status</th><th>Submitted</th></tr></thead><tbody>
            @forelse($reminders as $reminder)<tr><td><strong>{{ $reminder->user->name }}</strong><small>{{ '@'.$reminder->user->username }}</small></td><td>{{ $reminder->membership->plan->name }} · expires {{ $reminder->membership->expires_at->format('M j, Y') }}</td><td>{{ $reminder->phone }}</td><td>{{ $reminder->scheduled_for->format('M j, Y') }}</td><td><span class="status-pill {{ $reminder->status === 'submitted' ? 'active' : ($reminder->status === 'failed' ? 'expired' : 'upcoming') }}">{{ ucfirst($reminder->status) }}</span>@if($reminder->error)<small>{{ $reminder->error }}</small>@endif</td><td>{{ $reminder->submitted_at?->format('M j, Y · g:i A') ?? '—' }}</td></tr>
            @empty<tr><td colspan="6">No reminders queued yet.</td></tr>@endforelse
        </tbody></table></div><div class="pagination">{{ $reminders->links() }}</div>
    </section>
</section>
@endsection
