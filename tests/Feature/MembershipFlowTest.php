<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_membership_and_member_can_see_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'approval_status' => 'approved']);
        $member = User::factory()->create(['role' => 'member', 'approval_status' => 'approved']);
        $plan = MembershipPlan::where('name', 'Student')->firstOrFail();
        $this->actingAs($admin)->post('/admin/memberships', [
            'user_id' => $member->id, 'membership_plan_id' => $plan->id,
            'starts_at' => today()->toDateString(),
        ])->assertRedirect();
        $membership = Membership::firstOrFail();
        $this->assertSame(today()->addDays(29)->toDateString(), $membership->expires_at->toDateString());
        $this->actingAs($member)->get('/member/dashboard')->assertOk()->assertSee('Student')->assertSee('Active');
        $this->get('/admin/memberships')->assertForbidden();
    }

    public function test_overlapping_membership_is_rejected_and_only_expired_record_can_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'approval_status' => 'approved']);
        $member = User::factory()->create(['role' => 'member', 'approval_status' => 'approved']);
        $plan = MembershipPlan::where('name', 'Student')->firstOrFail();
        $active = Membership::create([
            'user_id' => $member->id, 'membership_plan_id' => $plan->id,
            'starts_at' => today(), 'expires_at' => today()->addDays(29),
        ]);
        $this->actingAs($admin)->post('/admin/memberships', [
            'user_id' => $member->id, 'membership_plan_id' => $plan->id,
            'starts_at' => today()->addDays(10)->toDateString(),
        ])->assertSessionHasErrors('starts_at');
        $this->delete('/admin/memberships/'.$active->id)->assertStatus(422);

        $expired = Membership::create([
            'user_id' => $member->id, 'membership_plan_id' => $plan->id,
            'starts_at' => today()->subDays(60), 'expires_at' => today()->subDays(31),
        ]);
        $this->delete('/admin/memberships/'.$expired->id)->assertRedirect();
        $this->assertDatabaseMissing('memberships', ['id' => $expired->id]);
    }
}
