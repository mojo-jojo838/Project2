<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Appeal;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MemberAccountController extends Controller
{
    private function requireAdmin(Request $request): void
    {
        abort_unless($request->user()->role === 'admin', 403);
    }

    public function index(Request $request): View
    {
        $this->requireAdmin($request);
        $filter = in_array($request->query('status'), ['approved', 'rejected', 'removed'], true)
            ? $request->query('status') : 'all';
        $query = $filter === 'removed' ? User::onlyTrashed() : User::query();
        $query->where('role', 'member')->where('approval_status', '!=', 'pending');
        if (in_array($filter, ['approved', 'rejected'], true)) {
            $query->where('approval_status', $filter);
        }
        return view('members.index', [
            'members' => $query->latest()->paginate(20)->withQueryString(),
            'filter' => $filter,
            'resetRequests' => Appeal::with('user')->latest()->paginate(10, ['*'], 'resetPage'),
            'pendingResetCount' => Appeal::where('status', 'pending')->count(),
        ]);
    }

    public function edit(Request $request, User $user): View
    {
        $this->requireAdmin($request);
        abort_unless($user->role === 'member', 404);
        return view('members.edit', ['member' => $user]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->requireAdmin($request);
        abort_unless($user->role === 'member', 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'alpha_dash', 'min:3', 'max:50', Rule::unique('users', 'username')->ignore($user->id)],
            'phone' => ['nullable', 'regex:/^(09\d{9}|\+639\d{9})$/'],
        ]);
        $phone = PhoneNumber::normalize($data['phone'] ?? null);
        if ($phone !== $user->phone) {
            $data['sms_consent'] = false;
            $data['sms_consented_at'] = null;
        }
        $data['phone'] = $phone;
        $user->update($data);
        return redirect()->route('admin.members.index')->with('status', 'Member account updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->requireAdmin($request);
        abort_unless($user->role === 'member', 404);
        $user->delete();
        return redirect()->route('admin.members.index')->with('status', 'Member account removed. Records are preserved.');
    }

    public function restore(Request $request, int $user): RedirectResponse
    {
        $this->requireAdmin($request);
        $member = User::onlyTrashed()->findOrFail($user);
        abort_unless($member->role === 'member', 404);
        $member->restore();
        return redirect()->route('admin.members.index', ['status' => 'removed'])->with('status', 'Member account restored.');
    }
}
