@extends('layouts.app')
@section('title', 'Payments')
@section('content')
<section class="dashboard">
    <div class="dashboard-head"><div><p class="kicker">ADMIN WORKSPACE</p><h1>Payment records.</h1><p>Membership and walk-in payments are recorded automatically.</p></div></div>
    <div class="stats payment-period-stats">
        <article><span>COLLECTED TODAY</span><strong>₱{{ number_format($todayTotal, 2) }}</strong></article>
        <article><span>COLLECTED THIS WEEK</span><strong>₱{{ number_format($weekTotal, 2) }}</strong></article>
        <article><span>COLLECTED THIS MONTH</span><strong>₱{{ number_format($monthTotal, 2) }}</strong></article>
        <article><span>COLLECTED THIS YEAR</span><strong>₱{{ number_format($yearTotal, 2) }}</strong></article>
    </div>
    <div class="payment-history-heading"><h2>Payment history</h2><div class="payment-date-controls"><form id="payment-date-form" method="GET" action="{{ route('admin.payments.index') }}"><label for="payment-date">Choose a date</label><input id="payment-date" name="date" type="date" value="{{ $selectedDate }}"></form>@if($selectedDate)<a href="{{ route('admin.payments.index') }}">All dates</a>@endif</div></div>
    @if($selectedDate)<p class="payment-selected-total">Collected on {{ \Illuminate\Support\Carbon::parse($selectedDate)->format('F j, Y') }}: <strong>₱{{ number_format($selectedDateTotal, 2) }}</strong></p>@endif
    <div class="table-wrap"><table><thead><tr><th>Person</th><th>Payment for</th><th>Amount received</th><th>Received on</th></tr></thead><tbody>
        @forelse($payments as $payment)
        <tr><td><strong>{{ $payment->user?->name ?? $payment->attendance?->walk_in_name ?? 'Former member' }}</strong>@if($payment->user)<small>{{ '@'.$payment->user->username }}</small>@endif</td>
            <td>{{ $payment->membership?->plan?->name ?? 'Walk-in' }}</td>
            <td>₱{{ number_format($payment->amount, 2) }}</td>
            <td>{{ $payment->paid_at?->format('M j, Y') ?? $payment->created_at->format('M j, Y') }}</td></tr>
        @empty <tr><td colspan="4">No payments yet. Assign a membership or record a walk-in to create one automatically.</td></tr> @endforelse
    </tbody></table></div><div class="pagination">{{ $payments->links() }}</div>
</section>
@endsection
@section('scripts')
<script>document.getElementById('payment-date').addEventListener('change', () => document.getElementById('payment-date-form').requestSubmit());</script>
@endsection
