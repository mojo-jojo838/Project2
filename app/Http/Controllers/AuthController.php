<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Membership;
use App\Models\Attendance;
use App\Models\Payment;
use App\Support\PhoneNumber;
use App\Services\AutomaticCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View { return view('auth.login'); }
    public function showRegister(): View { return view('auth.register'); }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['username' => ['required', 'string'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['username' => 'The username or password is incorrect.'])->onlyInput('username');
        }
        $request->session()->regenerate();
        if (Auth::user()->role !== 'admin' && Auth::user()->approval_status !== 'approved') {
            $approvalStatus = Auth::user()->approval_status;
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return back()->withErrors(['username' => $approvalStatus === 'rejected' ? 'Your registration was rejected. Contact the gym admin.' : 'Your account is awaiting gym admin approval.'])->onlyInput('username');
        }
        return redirect()->route(Auth::user()->role === 'admin' ? 'admin.dashboard' : 'member.dashboard');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'alpha_dash', 'min:3', 'max:50', 'unique:users,username'],
            'password' => ['required', 'confirmed', 'min:8'],
            'phone' => ['nullable', 'required_if:sms_consent,1', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'sms_consent' => ['nullable', 'boolean'],
        ]);
        User::create([
            'name' => $data['name'], 'username' => $data['username'],
            'password' => Hash::make($data['password']),
            'role' => 'member', 'approval_status' => 'pending',
            'phone' => PhoneNumber::normalize($data['phone'] ?? null),
            'sms_consent' => $request->boolean('sms_consent'),
            'sms_consented_at' => $request->boolean('sms_consent') ? now() : null,
        ]);
        return redirect()->route('login')->with('status', 'Registration submitted. You can sign in after an admin approves your account.');
    }

    public function dashboard(): RedirectResponse
    {
        return redirect()->route(Auth::user()->role === 'admin' ? 'admin.dashboard' : 'member.dashboard');
    }

    public function adminDashboard(): View|RedirectResponse
    {
        if (Auth::user()->role !== 'admin') return redirect()->route('member.dashboard');
        return view('dashboards.admin', [
            'pendingCount' => User::where('role', 'member')->where('approval_status', 'pending')->count(),
            'memberCount' => User::where('role', 'member')->where('approval_status', 'approved')->count(),
            'todayCount' => Attendance::whereDate('check_in_date', today())->count(),
            'pendingMembers' => User::where('role', 'member')->where('approval_status', 'pending')->latest()->get(),
        ]);
    }

    public function approveMember(User $user): RedirectResponse
    {
        abort_unless(Auth::user()->role === 'admin', 403);
        abort_unless($user->role === 'member' && $user->approval_status === 'pending', 404);
        $user->update(['approval_status' => 'approved', 'qr_token' => $user->qr_token ?: Str::random(48)]);
        return redirect()->route('admin.dashboard')->with('status', 'Member approved.');
    }

    public function rejectMember(User $user): RedirectResponse
    {
        abort_unless(Auth::user()->role === 'admin', 403);
        abort_unless($user->role === 'member' && $user->approval_status === 'pending', 404);
        $user->update(['approval_status' => 'rejected']);
        return redirect()->route('admin.dashboard')->with('status', 'Registration rejected.');
    }

    public function memberDashboard(): View|RedirectResponse
    {
        if (Auth::user()->role === 'admin') return redirect()->route('admin.dashboard');
        app(AutomaticCheckout::class)->closeOverdue();
        return view('dashboards.member', [
            'currentMembership' => Membership::with('plan')->where('user_id', Auth::id())
                ->whereDate('starts_at', '<=', today())
                ->whereDate('expires_at', '>=', today())->orderByDesc('expires_at')->first(),
            'latestMembership' => Membership::with('plan')->where('user_id', Auth::id())
                ->orderByDesc('expires_at')->first(),
            'checkIns' => Attendance::where('user_id', Auth::id())->latest('checked_in_at')
                ->paginate(20, ['*'], 'attendancePage')->withQueryString(),
            'payments' => Payment::with('membership.plan')->where('user_id', Auth::id())
                ->latest('created_at')->paginate(20, ['*'], 'paymentPage')->withQueryString(),
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
