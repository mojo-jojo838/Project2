@extends('layouts.app')
@section('title', 'Sign in')
@section('content')
<section class="auth-layout">
    <div class="intro-panel login-intro">
        <div class="eyebrow"><span class="line"></span> YOUR SPACE TO GROW</div>
        <h1>DARIUS 
            FITNESS GYM<span class="accent">.</span></h1>
        <p>A fitness place where you can truly experience physical transformation after just a few months</p>
        <div class="intro-rule"></div>
        <div class="intro-caption"><span class="caption-number">01 / 03</span><span>SHOW UP. PUT IN THE WORK. GROW STRONGER.</span></div>
    </div>
    <div class="form-panel">
        <div class="form-topline">ACCOUNT ACCESS <span>01</span></div>
        <div class="form-heading"><p class="kicker">D'FITNESS GYM</p><h2>Sign in</h2><p>Enter your account details to continue.</p></div>
        @if(session('status')) <div class="notice success" role="status">{{ session('status') }}</div> @endif
        @if($errors->any()) <div class="notice error" role="alert">{{ $errors->first() }}</div> @endif
        <form method="POST" action="{{ route('login.submit') }}">
            @csrf
            <label for="username">Username</label>
            <input id="username" type="text" name="username" value="{{ old('username') }}" placeholder="Enter your username" required autofocus autocomplete="username">
            <label for="password">Password</label>
            <div class="password-field"><input id="password" type="password" name="password" placeholder="Enter your password" required autocomplete="current-password"><button type="button" class="password-toggle" data-password-target="password" aria-label="Show password" aria-pressed="false">Show</button></div>
            <label class="check-row"><input type="checkbox" name="remember" value="1"> <span>Keep me signed in</span></label>
            <button class="primary-button" type="submit">Sign in <span aria-hidden="true">→</span></button>
        </form>
        <div class="form-divider"><span>NEW TO D'FITNESS?</span></div>
        <a class="secondary-button" href="{{ route('register') }}">Create a member account <span aria-hidden="true">→</span></a>
        <p class="fine-print">Member registrations are reviewed by the gym admin before access is granted.</p>
        <p class="fine-print">Forgot your password? <a href="{{ route('appeals.create') }}">Request a reset from the gym admin</a>.</p>
        <p class="fine-print">Already have a reset code? <a href="{{ route('password.reset.form') }}">Set a new password</a>.</p>
    </div>
</section>
@endsection
