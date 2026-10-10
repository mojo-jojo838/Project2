@extends('layouts.app')
@section('title', 'Set new password')
@section('content')
<section class="auth-layout">
    <div class="intro-panel"><div class="eyebrow"><span class="line"></span> ACCOUNT SUPPORT</div><h1>Set a new password<span class="accent">.</span></h1><p>Use the five-digit code given to you by the gym admin. The code expires 15 minutes after approval.</p></div>
    <div class="form-panel">
        <div class="form-topline">PASSWORD RESET <span>03</span></div>
        <div class="form-heading"><p class="kicker">D'FITNESS GYM</p><h2>New password</h2><p>Enter your username, reset code, and new password.</p></div>
        @if($errors->any()) <div class="notice error" role="alert">{{ $errors->first() }}</div> @endif
        <form method="POST" action="{{ route('password.reset.submit') }}">@csrf
            <label for="username">Username</label><input id="username" name="username" value="{{ old('username') }}" required maxlength="50" autocomplete="username">
            <label for="code">Five-digit authentication code</label><input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{5}" required minlength="5" maxlength="5" autocomplete="one-time-code">
            <label for="password">New password</label><div class="password-field"><input id="password" type="password" name="password" required minlength="8" autocomplete="new-password"><button type="button" class="password-toggle" data-password-target="password" aria-label="Show password" aria-pressed="false">Show</button></div>
            <label for="password_confirmation">Confirm new password</label><div class="password-field"><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"><button type="button" class="password-toggle" data-password-target="password_confirmation" aria-label="Show confirm password" aria-pressed="false">Show</button></div>
            <button class="primary-button" type="submit">Save new password</button>
        </form><p class="fine-print"><a href="{{ route('login') }}">Back to sign in</a></p>
    </div>
</section>
@endsection
