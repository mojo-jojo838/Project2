@extends('layouts.app')
@section('title', 'Member QR scanner')
@section('content')
<section class="dashboard scanner-page">
    <div class="dashboard-head">
        <div><p class="kicker">ADMIN WORKSPACE</p><h1>Member QR scanner</h1><p>Point the camera at a member's QR code to record check-in or check-out automatically.</p></div>
        <div class="scanner-page-count"><strong id="today-count">{{ $todayCount }}</strong><span>check-ins today</span></div>
    </div>
    <section class="management-card scanner-page-card" aria-label="Member QR scanning camera">
        <div class="camera-area"><video id="scanner-video" playsinline muted hidden></video><div id="scan-confirmation" class="scan-confirmation" hidden><strong id="scan-confirmation-title"></strong><span id="scan-confirmation-name"></span></div></div>
        <p id="scanner-message" class="scanner-status" role="status">Starting camera…</p>
        <div class="camera-actions"><button id="start-scan" class="secondary-button" type="button">Retry camera</button><button id="stop-scan" class="secondary-button" type="button" hidden>Stop camera</button></div>
        <audio id="scan-confirmation-sound" src="{{ asset('audio/checkin-checkout-confirmation.m4a') }}" preload="auto"></audio>
        <audio id="scan-error-sound" src="{{ asset('audio/scan-error.mp3') }}" preload="auto"></audio>
        <div id="scanner-config" data-url="{{ route('admin.attendance.check-in') }}" data-token="{{ csrf_token() }}"></div>
    </section>
</section>
@endsection
@section('scripts')
<script src="{{ asset('js/jsQR.js') }}"></script>
<script src="{{ asset('js/attendance-scanner.js') }}?v=14"></script>
@endsection
