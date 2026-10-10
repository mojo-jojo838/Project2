@extends('layouts.app')
@section('title', 'Request password reset')
@section('content')
<section class="auth-layout">
    <div class="intro-panel"><div class="eyebrow"><span class="line"></span> ACCOUNT SUPPORT</div><h1>Forgot your password<span class="accent">?</span></h1><p>Send a request to the gym admin. After they verify your identity, they can give you a one-time code to set a new password.</p></div>
    <div class="form-panel">
        <div class="form-topline">ACCOUNT SUPPORT <span>02</span></div>
        <div class="form-heading"><p class="kicker">D'FITNESS GYM</p><h2>Request a reset</h2><p>Enter your username and explain that you need a new password.</p></div>
        @if($errors->any()) <div class="notice error" role="alert">{{ $errors->first() }}</div> @endif
        <form method="POST" action="{{ route('appeals.store') }}">@csrf
            <label for="username">Username</label><input id="username" name="username" value="{{ old('username') }}" required maxlength="50" autocomplete="username" autofocus>
            <label for="message">Your message</label><textarea id="message" name="message" rows="6" minlength="10" maxlength="2000" required>{{ old('message') }}</textarea>
            <button class="primary-button" type="submit">Request password reset</button>
        </form>
        <p class="fine-print">Do not include your current or new password in this message. Once the admin gives you a code, <a href="{{ route('password.reset.form') }}">set your new password here</a>.</p>
        <p class="fine-print"><a href="{{ route('login') }}">Back to sign in</a></p>
    </div>
</section>
@endsection
