@extends('layouts.app')
@section('title', 'Register')
@section('content')
<section class="auth-layout">
    <div class="intro-panel"><div class="eyebrow"><span class="line"></span> GET STARTED</div><h1>Join the gym<span class="accent">.</span></h1><p>Create your member account. A gym admin will review your registration before you can sign in.</p><div class="intro-rule"></div><div class="intro-caption"><span class="caption-number">02 / 03</span><span>START YOUR JOURNEY HERE.</span></div></div>
    <div class="form-panel"><div class="form-topline">MEMBER REGISTRATION <span>02</span></div><div class="form-heading"><p class="kicker">D'FITNESS GYM</p><h2>Create account</h2><p>Complete the details below to request access.</p></div>
        @if($errors->any()) <div class="notice error" role="alert">{{ $errors->first() }}</div> @endif
        <form method="POST" action="{{ route('register.submit') }}">@csrf
            <label for="name">Full name</label><input id="name" name="name" value="{{ old('name') }}" required autocomplete="name" placeholder="Your full name">
            <label for="username">Username</label><input id="username" type="text" name="username" value="{{ old('username') }}" required minlength="3" maxlength="50" autocomplete="username" placeholder="Choose a unique username">
            <label for="phone">Mobile number (optional)</label><input id="phone" type="tel" name="phone" value="{{ old('phone') }}" placeholder="09XXXXXXXXX" autocomplete="tel">
            <label class="check-row"><input type="checkbox" name="sms_consent" value="1" @checked(old('sms_consent'))><span>I agree to receive membership renewal reminders by SMS.</span></label>
            <label for="password">Password</label><div class="password-field"><input id="password" type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters"><button type="button" class="password-toggle" data-password-target="password" aria-label="Show password" aria-pressed="false">Show</button></div>
            <label for="password_confirmation">Confirm password</label><div class="password-field"><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Repeat your password"><button type="button" class="password-toggle" data-password-target="password_confirmation" aria-label="Show confirm password" aria-pressed="false">Show</button></div>
            <button class="primary-button" type="submit">Submit registration <span aria-hidden="true">→</span></button>
        </form><p class="fine-print">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
    </div>
</section>
@endsection
