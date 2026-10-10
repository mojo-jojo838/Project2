@extends('layouts.app')
@section('title', 'Edit member')
@section('content')
<section class="dashboard"><div class="dashboard-head"><div><p class="kicker">MEMBER ACCOUNTS</p><h1>Edit member.</h1><p>Update the member's name or username.</p></div><a class="secondary-button" href="{{ route('admin.members.index') }}">Back to members</a></div>
    @if($errors->any()) <div class="notice error" role="alert">{{ $errors->first() }}</div> @endif
    <section class="management-card edit-member-card"><form method="POST" action="{{ route('admin.members.update', $member) }}">@csrf @method('PUT')<label for="member-name">Full name</label><input id="member-name" name="name" required maxlength="255" value="{{ old('name', $member->name) }}"><label for="member-username">Username</label><input id="member-username" name="username" required minlength="3" maxlength="50" value="{{ old('username', $member->username) }}"><label for="member-phone">Mobile number</label><input id="member-phone" name="phone" type="tel" value="{{ old('phone', $member->phone) }}" placeholder="09XXXXXXXXX"><p class="card-help">Changing the number turns off SMS reminders until the member opts in again.</p><button class="primary-button" type="submit">Save changes</button></form></section>
</section>
@endsection
