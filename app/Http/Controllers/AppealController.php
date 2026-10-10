<?php

namespace App\Http\Controllers;

use App\Models\Appeal;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppealController extends Controller
{
    public function create(): View
    {
        return view('appeals.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        Appeal::create([
            'user_id' => User::withTrashed()->where('username', $data['username'])->value('id'),
            'username' => $data['username'],
            'message' => $data['message'],
        ]);

        return redirect()->route('login')->with('status', 'Password reset request submitted. Ask the gym admin to review it.');
    }

    public function index(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        return redirect(route('admin.members.index').'#password-resets');
    }

    public function update(Request $request, Appeal $appeal): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $data = $request->validate([
            'status' => ['required', 'in:approved,declined'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);
        abort_unless($appeal->status === 'pending', 422);
        if ($data['status'] === 'approved') {
            abort_unless($appeal->user && $appeal->user->role === 'member' && $appeal->user->approval_status === 'approved' && ! $appeal->user->trashed(), 422, 'This request is not linked to an approved member account.');
            $code = sprintf('%05d', random_int(0, 99999));
            DB::transaction(function () use ($appeal, $data, $code) {
                Appeal::where('user_id', $appeal->user_id)->where('status', 'approved')
                    ->update(['status' => 'superseded', 'reset_code_hash' => null]);
                $appeal->update([
                    'status' => 'approved',
                    'admin_note' => $data['admin_note'] ?? null,
                    'reviewed_at' => now(),
                    'reset_code_hash' => Hash::make($code),
                    'reset_expires_at' => now()->addMinutes(15),
                ]);
            });
            return redirect(route('admin.members.index').'#password-resets')
                ->with('reset_code', $code)->with('reset_username', $appeal->username);
        }
        $appeal->update([
            'status' => 'declined',
            'admin_note' => $data['admin_note'] ?? null,
            'reviewed_at' => now(),
        ]);

        return redirect(route('admin.members.index').'#password-resets')->with('status', 'Password reset request declined.');
    }

    public function resetForm(): View
    {
        return view('appeals.reset');
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'code' => ['required', 'regex:/^\d{5}$/'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);
        $limitKey = 'password-reset:'.sha1(strtolower($data['username']));
        if (RateLimiter::tooManyAttempts($limitKey, 5)) {
            return back()->withErrors(['code' => 'Too many attempts. Try again in 15 minutes.'])->onlyInput('username');
        }
        RateLimiter::hit($limitKey, 900);
        $appeal = Appeal::with('user')->where('username', $data['username'])
            ->where('status', 'approved')->whereNull('reset_used_at')
            ->where('reset_expires_at', '>', now())->latest()->get()
            ->first(fn (Appeal $appeal) => $appeal->reset_code_hash && Hash::check($data['code'], $appeal->reset_code_hash));
        if (! $appeal || ! $appeal->user || $appeal->user->role !== 'member' || $appeal->user->trashed()) {
            return back()->withErrors(['code' => 'The reset details are invalid or expired.'])->onlyInput('username');
        }
        DB::transaction(function () use ($appeal, $data) {
            $locked = Appeal::whereKey($appeal->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'approved' && ! $locked->reset_used_at && $locked->reset_expires_at->isFuture(), 422);
            $locked->user->update(['password' => Hash::make($data['password']), 'remember_token' => Str::random(60)]);
            $locked->update(['status' => 'completed', 'reset_used_at' => now(), 'reset_code_hash' => null]);
        });
        RateLimiter::clear($limitKey);
        return redirect()->route('login')->with('status', 'Password changed. You can now sign in.');
    }
}
