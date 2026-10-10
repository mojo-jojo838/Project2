<?php

namespace App\Http\Controllers;

use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MemberContactController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'member', 403);
        $data = $request->validate([
            'phone' => ['nullable', 'required_if:sms_consent,1', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'sms_consent' => ['nullable', 'boolean'],
        ]);
        $consent = $request->boolean('sms_consent');
        $request->user()->update([
            'phone' => PhoneNumber::normalize($data['phone'] ?? null),
            'sms_consent' => $consent,
            'sms_consented_at' => $consent ? now() : null,
        ]);
        return back()->with('status', 'Contact preferences updated.');
    }
}
