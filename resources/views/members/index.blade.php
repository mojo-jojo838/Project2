@extends('layouts.app')
@section('title', 'Member accounts')
@section('content')
<section class="dashboard">
    <div class="dashboard-head"><div><p class="kicker">ADMIN WORKSPACE</p><h1>Member accounts.</h1><p>Maintain approved accounts and review rejected registrations.</p></div></div>
    @if(session('status')) <div class="notice success" role="status">{{ session('status') }}</div> @endif
    @if($errors->any()) <div class="notice error" role="alert">{{ $errors->first() }}</div> @endif
    <nav class="filter-tabs" aria-label="Member account filters"><a class="{{ $filter === 'all' ? 'selected' : '' }}" href="{{ route('admin.members.index') }}">All</a><a class="{{ $filter === 'approved' ? 'selected' : '' }}" href="{{ route('admin.members.index', ['status' => 'approved']) }}">Approved</a><a class="{{ $filter === 'rejected' ? 'selected' : '' }}" href="{{ route('admin.members.index', ['status' => 'rejected']) }}">Rejected</a><a class="{{ $filter === 'removed' ? 'selected' : '' }}" href="{{ route('admin.members.index', ['status' => 'removed']) }}">Removed</a></nav>
    <div class="table-wrap"><table><thead><tr><th>Member</th><th>Account status</th><th>Registered</th><th>Actions</th></tr></thead><tbody>
        @forelse($members as $member)<tr><td><strong>{{ $member->name }}</strong><small>{{ '@'.$member->username }}</small></td><td><span class="status-pill {{ $member->trashed() ? 'expired' : ($member->approval_status === 'approved' ? 'active' : 'expired') }}">{{ $member->trashed() ? 'Removed' : ucfirst($member->approval_status) }}</span></td><td>{{ $member->created_at->format('M j, Y') }}</td><td><div class="row-actions">@if($member->trashed())<form method="POST" action="{{ route('admin.members.restore', $member->id) }}">@csrf<button class="text-button" type="submit">Restore</button></form>@else
            <a href="{{ route('admin.members.edit', $member) }}">Edit</a><form method="POST" action="{{ route('admin.members.destroy', $member) }}" onsubmit="return confirm('Remove this member account? Its records will be kept and the account can be restored.')">@csrf @method('DELETE')<button class="text-button" type="submit">Remove</button></form>@endif</div></td></tr>
        @empty<tr><td colspan="4">No member accounts in this view.</td></tr>@endforelse
    </tbody></table></div><div class="pagination">{{ $members->links() }}</div>
    @include('members.password_resets')
</section>
@endsection
