<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_approved_member_use_the_same_login_with_different_destinations(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'approval_status' => 'approved']);
        $member = User::factory()->create(['role' => 'member', 'approval_status' => 'approved']);

        $this->post('/login', ['username' => $admin->username, 'password' => 'password'])
            ->assertRedirect('/admin/dashboard');
        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['username' => $member->username, 'password' => 'password'])
            ->assertRedirect('/member/dashboard');
        $this->get('/admin/dashboard')->assertRedirect('/member/dashboard');
    }

    public function test_new_members_wait_for_admin_approval(): void
    {
        $this->post('/register', [
            'name' => 'New Member', 'username' => 'newmember',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect('/login');

        $member = User::where('username', 'newmember')->firstOrFail();
        $this->assertSame('member', $member->role);
        $this->assertSame('pending', $member->approval_status);
        $this->post('/login', ['username' => $member->username, 'password' => 'password123'])
            ->assertSessionHasErrors('username');

        $admin = User::factory()->create(['role' => 'admin', 'approval_status' => 'approved']);
        $this->actingAs($admin)->post('/admin/members/'.$member->id.'/approve')->assertRedirect('/admin/dashboard');
        $this->assertSame('approved', $member->fresh()->approval_status);
    }

    public function test_member_cannot_approve_accounts(): void
    {
        $member = User::factory()->create(['role' => 'member', 'approval_status' => 'approved']);
        $pending = User::factory()->create(['role' => 'member', 'approval_status' => 'pending']);
        $this->actingAs($member)->post('/admin/members/'.$pending->id.'/approve')->assertForbidden();
    }

    public function test_member_username_cannot_be_registered_twice(): void
    {
        User::factory()->create(['role' => 'member', 'username' => 'takenname']);
        $registration = [
            'name' => 'Another Member', 'username' => 'takenname',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ];
        $this->post('/register', $registration)->assertSessionHasErrors('username');
        $this->assertSame(1, User::where('username', 'takenname')->count());
    }

    public function test_pending_registration_is_only_on_dashboard_and_can_be_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'approval_status' => 'approved']);
        $pending = User::factory()->create(['role' => 'member', 'approval_status' => 'pending', 'username' => 'pendingtest']);

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->assertSee('pendingtest')->assertSee('Reject');
        $this->get('/admin/members')->assertOk()->assertDontSee('pendingtest');
        $this->post('/admin/members/'.$pending->id.'/reject')->assertRedirect('/admin/dashboard');
        $this->assertSame('rejected', $pending->fresh()->approval_status);
        $this->get('/admin/dashboard')->assertDontSee('pendingtest');
        $this->get('/admin/members?status=rejected')->assertSee('pendingtest');
        $this->post('/admin/members/'.$pending->id.'/approve')->assertNotFound();
        $this->post('/logout');
        $this->post('/login', ['username' => 'pendingtest', 'password' => 'password'])
            ->assertSessionHasErrors('username');
    }
}
