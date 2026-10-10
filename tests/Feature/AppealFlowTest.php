<?php

namespace Tests\Feature;

use App\Models\Appeal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AppealFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_password_reset_requires_admin_approval_and_uses_code_once(): void
    {
        $member = User::factory()->create(['role' => 'member', 'approval_status' => 'approved', 'username' => 'gymmember']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->get('/appeal')->assertOk();
        $this->post('/appeal', ['username' => 'gymmember', 'message' => 'I forgot my password and need help.'])->assertRedirect(route('login'));
        $appeal = Appeal::firstOrFail();
        $this->assertSame($member->id, $appeal->user_id);
        $data = ['username' => 'gymmember', 'code' => '12345', 'password' => 'newpassword123', 'password_confirmation' => 'newpassword123'];
        $this->post('/reset-password', $data)->assertSessionHasErrors('code');
        $this->actingAs($member)->get('/admin/appeals')->assertForbidden();
        $this->patch('/admin/appeals/'.$appeal->id, ['status' => 'approved'])->assertForbidden();
        $this->actingAs($admin)->get('/admin/members')->assertOk()->assertSee('Password reset requests')->assertSee('gymmember');
        $response = $this->actingAs($admin)->patch('/admin/appeals/'.$appeal->id, ['status' => 'approved']);
        $response->assertRedirect()->assertSessionHas('reset_code');
        $code = session('reset_code');
        $this->assertMatchesRegularExpression('/^\d{5}$/', $code);
        $this->assertSame('approved', $appeal->fresh()->status);
        $this->post('/logout');
        $data['code'] = $code;
        $this->post('/reset-password', $data)->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('newpassword123', $member->fresh()->password));
        $this->assertSame('completed', $appeal->fresh()->status);
        $this->post('/reset-password', $data)->assertSessionHasErrors('code');
    }

    public function test_unknown_account_cannot_be_approved(): void
    {
        $this->post('/appeal', ['username' => 'unknown', 'message' => 'Please help me reset my password.'])->assertRedirect(route('login'));
        $appeal = Appeal::firstOrFail();
        $this->assertNull($appeal->user_id);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patch('/admin/appeals/'.$appeal->id, ['status' => 'approved'])->assertStatus(422);
        $this->patch('/admin/appeals/'.$appeal->id, ['status' => 'declined'])->assertRedirect();
        $this->assertSame('declined', $appeal->fresh()->status);
    }

    public function test_expired_reset_code_cannot_change_password(): void
    {
        $member = User::factory()->create(['role' => 'member', 'username' => 'member2']);
        $appeal = Appeal::create([
            'user_id' => $member->id, 'username' => 'member2', 'message' => 'I forgot my password.',
            'status' => 'approved', 'reset_code_hash' => Hash::make('12345'),
            'reset_expires_at' => now()->subMinute(),
        ]);
        $oldPassword = $member->password;
        $this->post('/reset-password', [
            'username' => 'member2', 'code' => '12345',
            'password' => 'newpassword123', 'password_confirmation' => 'newpassword123',
        ])->assertSessionHasErrors('code');
        $this->assertSame($oldPassword, $member->fresh()->password);
        $this->assertSame('approved', $appeal->fresh()->status);
    }

}
