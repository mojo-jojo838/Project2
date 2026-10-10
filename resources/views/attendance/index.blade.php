@extends('layouts.app')
@section('title', 'Attendance')
@section('content')
<section class="dashboard attendance-dashboard">
    <div class="dashboard-head"><div><p class="kicker">ADMIN WORKSPACE</p><h1>Daily attendance.</h1><p>Review attendance and record walk-in visitors.</p></div></div>
    <div class="stats"><article><span>CHECK-INS TODAY</span><strong id="today-count">{{ $todayCount }}</strong></article></div>
    @if(session('status')) <div class="notice success" role="status">{{ session('status') }}</div> @endif
    @if($errors->any()) <div class="notice error" role="alert">{{ $errors->first() }}</div> @endif
    <div class="management-grid attendance-management-grid">
        <section class="management-card"><p class="kicker">VISITOR CHECK-IN</p><h2>Record a walk-in</h2><p class="card-help">Walk-in rate: ₱120.00 per visit. Payment is recorded automatically with the visit.</p><form method="POST" action="{{ route('admin.attendance.walk-in') }}">@csrf<label for="walk-in-name">Visitor name</label><input id="walk-in-name" name="walk_in_name" required maxlength="255" value="{{ old('walk_in_name') }}" placeholder="Full name"><button class="primary-button" type="submit">Record walk-in and payment</button></form></section>
        <section class="management-card attendance-history-card"><p class="kicker">ATTENDANCE RECORDS</p><h2>Attendance history</h2>
            <form class="attendance-filter-form" method="GET" action="{{ route('admin.attendance.index') }}">
                <div><label for="attendance-type">Type</label><select id="attendance-type" name="type"><option value="all" @selected($selectedType === 'all')>All visitors</option><option value="qr" @selected($selectedType === 'qr')>Registered member</option><option value="walk-in" @selected($selectedType === 'walk-in')>Walk-in</option></select></div>
                <div><label for="attendance-period">Date</label><select id="attendance-period" name="period"><option value="all" @selected($selectedPeriod === 'all')>All dates</option><option value="today" @selected($selectedPeriod === 'today')>Today</option><option value="custom" @selected($selectedPeriod === 'custom')>Choose a date</option></select></div>
                <div id="attendance-date-field" @if($selectedPeriod !== 'custom') hidden @endif><label for="attendance-date">Choose a date</label><input id="attendance-date" type="date" name="date" value="{{ $selectedDate }}" @if($selectedPeriod !== 'custom') disabled @endif></div>
            </form>
            <div class="table-wrap"><table><thead><tr><th>Visitor</th><th>Type</th><th>Checked in</th><th>Checked out</th></tr></thead><tbody>
                @forelse($records as $record)<tr><td><strong>{{ $record->user?->name ?? $record->walk_in_name ?? 'Former member' }}</strong>@if($record->user)<small>{{ '@'.$record->user->username }}</small>@endif</td><td>{{ $record->method === 'qr' ? 'Registered member' : 'Walk-in' }}</td><td>{{ $record->checked_in_at->format('M j, Y · g:i A') }}</td><td>{{ $record->checked_out_at?->format('M j, Y · g:i A') ?? '—' }}@if($record->auto_checked_out)<small>Automatic checkout</small>@endif</td></tr>
                @empty <tr><td colspan="4">No attendance records match these filters.</td></tr> @endforelse
            </tbody></table></div><div class="pagination">{{ $records->links() }}</div>
        </section>
    </div>
</section>
@endsection
@section('scripts')
<script>
    (() => {
        const form = document.querySelector('.attendance-filter-form');
        const type = document.getElementById('attendance-type');
        const period = document.getElementById('attendance-period');
        const field = document.getElementById('attendance-date-field');
        const date = document.getElementById('attendance-date');
        function updateDateField() {
            const custom = period.value === 'custom';
            field.hidden = !custom;
            date.disabled = !custom;
            date.required = custom;
        }
        function submitFilters() {
            if (period.value !== 'custom' || date.value) form.requestSubmit();
        }
        type.addEventListener('change', submitFilters);
        period.addEventListener('change', () => {
            updateDateField();
            submitFilters();
        });
        date.addEventListener('change', submitFilters);
        updateDateField();
    })();
</script>
@endsection
