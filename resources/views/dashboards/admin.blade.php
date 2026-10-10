@extends('layouts.app')
@section('title', 'Admin dashboard')
@section('content')
<section class="dashboard admin-dashboard">
    <div class="admin-dashboard-top">
        <div><p class="kicker">ADMIN WORKSPACE</p><h1>Gym dashboard</h1><p>Welcome, {{ auth()->user()->name }}. Manage your gym from one place.</p></div>
    </div>

    @if(session('status')) <div class="notice success" role="status">{{ session('status') }}</div> @endif

    <div class="admin-overview">
        <article class="admin-overview-card"><span>REGISTERED MEMBERS</span><strong>{{ $memberCount }}</strong><p>Members with access to the gym system</p></article>
        <section class="admin-scanner-card" aria-label="Member QR scanner">
            <div class="admin-scanner-heading"><div><p class="kicker">MEMBER ATTENDANCE</p><p class="admin-scanner-help">Scan a member's QR code to record check-in or check-out.</p></div><p><strong id="today-count">{{ $todayCount }}</strong> check-ins today</p></div>
            <a class="primary-button admin-scanner-launch" href="{{ route('admin.attendance.scanner') }}">Open QR code scanner</a>
        </section>
    </div>

    <nav class="admin-shortcuts" aria-label="Gym management">
        <a href="{{ route('admin.memberships.index') }}"><i class="fi fi-br-membership shortcut-icon" aria-hidden="true"></i><strong>Memberships</strong><small>Plans and reminders</small></a>
        <a href="{{ route('admin.attendance.index') }}"><i class="fi fi-br-calendar shortcut-icon" aria-hidden="true"></i><strong>Attendance</strong><small>Record a walk-in and Attendance History</small></a>
        <a href="{{ route('admin.payments.index') }}"><i class="fi fi-br-wallet shortcut-icon" aria-hidden="true"></i><strong>Payments</strong><small>Collected totals and history</small></a>
        <a href="{{ route('admin.members.index') }}"><i class="fi fi-br-users-gear shortcut-icon" aria-hidden="true"></i><strong>Member accounts</strong><small>Review and manage</small></a>
    </nav>

    <section class="admin-registration-panel" aria-labelledby="pending-heading">
        <div class="admin-panel-heading"><div><p class="kicker">MEMBER REGISTRATION</p><h2 id="pending-heading">Pending registrations</h2></div><span class="status-pill upcoming">{{ $pendingCount }} pending</span></div>
        <div class="table-wrap"><table><thead><tr><th>Member</th><th>Username</th><th>Registered</th><th>Action</th></tr></thead><tbody>
            @forelse($pendingMembers as $member)
            <tr><td><strong>{{ $member->name }}</strong></td><td>{{ '@'.$member->username }}</td><td>{{ $member->created_at->format('M j, Y') }}</td><td><div class="registration-actions"><form method="POST" action="{{ route('admin.members.approve', $member) }}">@csrf<button class="approve-button" type="submit">Approve</button></form><form method="POST" action="{{ route('admin.members.reject', $member) }}" onsubmit="return confirm('Reject this registration?')">@csrf<button class="reject-button" type="submit">Reject</button></form></div></td></tr>
            @empty <tr><td colspan="4">No registrations are awaiting approval.</td></tr> @endforelse
        </tbody></table></div>
    </section>
</section>
@endsection
