<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberAccountFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_edits_removes_and_restores_member_without_losing_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'approval_status' => 'approved']);
        $member = User::factory()->create(['role' => 'member', 'approval_status' => 'approved']);
        $plan = MembershipPlan::where('name', 'Student')->firstOrFail();
        $membership = Membership::create(['user_id' => $member->id, 'membership_plan_id' => $plan->id, 'starts_at' => today(), 'expires_at' => today()->addDays(29)]);
        Payment::create(['user_id' => $member->id, 'membership_id' => $membership->id, 'amount' => '900.00', 'status' => 'paid', 'paid_at' => today(), 'method' => 'automatic']);
        Attendance::create(['user_id' => $member->id, 'method' => 'qr', 'check_in_date' => today(), 'checked_in_at' => now()]);

        $this->actingAs($admin)->put('/admin/members/'.$member->id, ['name' => 'Updated Member', 'username' => 'updatedmember'])->assertRedirect('/admin/members');
        $this->delete('/admin/members/'.$member->id)->assertRedirect('/admin/members');
        $this->assertSoftDeleted('users', ['id' => $member->id]);
        $this->assertSame(1, Membership::count());
        $this->assertSame(1, Payment::count());
        $this->assertSame(1, Attendance::count());
        $this->get('/admin/payments')->assertOk()->assertSee('Updated Member');
        $this->get('/admin/attendance')->assertOk()->assertSee('Updated Member');
        $this->post('/logout');
        $this->post('/login', ['username' => 'updatedmember', 'password' => 'password'])->assertSessionHasErrors('username');
        $this->actingAs($admin)->post('/admin/members/'.$member->id.'/restore')->assertRedirect();
        $this->assertFalse($member->fresh()->trashed());
        $this->post('/logout');
        $this->post('/login', ['username' => 'updatedmember', 'password' => 'password'])->assertRedirect('/member/dashboard');
    }

    public function test_member_cannot_manage_accounts_and_admin_account_is_protected(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'approval_status' => 'approved']);
        $member = User::factory()->create(['role' => 'member', 'approval_status' => 'approved']);
        $this->actingAs($member)->get('/admin/members')->assertForbidden();
        $this->delete('/admin/members/'.$member->id)->assertForbidden();
        $this->actingAs($admin)->delete('/admin/members/'.$admin->id)->assertNotFound();
        $this->put('/admin/members/'.$admin->id, ['name' => 'Changed', 'username' => 'changed'])->assertNotFound();
    }

    public function test_admin_cannot_change_member_username_to_an_existing_one(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'member', 'username' => 'alreadyused']);
        $member = User::factory()->create(['role' => 'member', 'username' => 'othermember']);

        $this->actingAs($admin)->put('/admin/members/'.$member->id, [
            'name' => $member->name, 'username' => 'alreadyused',
        ])->assertSessionHasErrors('username');
        $this->assertSame('othermember', $member->fresh()->username);
    }
}
