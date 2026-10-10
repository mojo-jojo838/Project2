<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\SmsReminder;
use App\Models\User;
use App\Services\RenewalReminders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RenewalReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_finds_reminders_within_memberships(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'approval_status' => 'approved']);

        $this->actingAs($admin)->get('/admin/memberships')
            ->assertOk()
            ->assertSee('Renewal reminders')
            ->assertSee('Queue and send reminders now');

        $this->get('/admin/reminders')
            ->assertRedirect(route('admin.memberships.index').'#renewal-reminders');
    }

    public function test_only_consented_unrenewed_members_are_queued_once(): void
    {
        $plan = MembershipPlan::where('name', 'Student')->firstOrFail();
        $eligible = User::factory()->create(['role' => 'member', 'approval_status' => 'approved', 'phone' => '+639171234567', 'sms_consent' => true]);
        $noConsent = User::factory()->create(['role' => 'member', 'approval_status' => 'approved', 'phone' => '+639181234567', 'sms_consent' => false]);
        $renewed = User::factory()->create(['role' => 'member', 'approval_status' => 'approved', 'phone' => '+639191234567', 'sms_consent' => true]);
        foreach ([$eligible, $noConsent, $renewed] as $member) {
            Membership::create(['user_id' => $member->id, 'membership_plan_id' => $plan->id, 'starts_at' => today()->subDays(26), 'expires_at' => today()->addDays(3)]);
        }
        Membership::create(['user_id' => $renewed->id, 'membership_plan_id' => $plan->id, 'starts_at' => today()->addDays(4), 'expires_at' => today()->addDays(33)]);

        $service = app(RenewalReminders::class);
        $this->assertSame(1, $service->queueDue());
        $this->assertSame(0, $service->queueDue());
        $this->assertDatabaseHas('sms_reminders', ['user_id' => $eligible->id, 'status' => 'queued']);
        $this->assertSame(1, SmsReminder::count());
    }

    public function test_sms_is_not_sent_without_credentials_and_can_be_submitted_to_gateway(): void
    {
        $member = User::factory()->create(['role' => 'member', 'approval_status' => 'approved', 'phone' => '+639171234567', 'sms_consent' => true]);
        $plan = MembershipPlan::where('name', 'Regular')->firstOrFail();
        $membership = Membership::create(['user_id' => $member->id, 'membership_plan_id' => $plan->id, 'starts_at' => today()->subDays(26), 'expires_at' => today()->addDays(3)]);
        $service = app(RenewalReminders::class);
        $service->queueDue();

        config()->set('services.twilio', ['account_sid' => null, 'auth_token' => null, 'from' => null]);
        $this->assertFalse($service->sendQueued()['configured']);
        $this->assertSame('queued', SmsReminder::where('membership_id', $membership->id)->firstOrFail()->status);

        config()->set('services.twilio', ['account_sid' => 'ACtest', 'auth_token' => 'secret', 'from' => '+15005550006']);
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SMtest'], 201)]);
        $this->assertSame(1, $service->sendQueued()['submitted']);
        $this->assertSame('submitted', SmsReminder::firstOrFail()->status);
        Http::assertSent(fn ($request) => $request['To'] === '+639171234567' && $request['Body'] !== '');
    }

    public function test_admin_manual_action_queues_and_submits_when_gateway_is_configured(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'approval_status' => 'approved']);
        $member = User::factory()->create(['role' => 'member', 'approval_status' => 'approved', 'phone' => '+639171234567', 'sms_consent' => true]);
        $plan = MembershipPlan::where('name', 'Student')->firstOrFail();
        Membership::create(['user_id' => $member->id, 'membership_plan_id' => $plan->id, 'starts_at' => today()->subDays(26), 'expires_at' => today()->addDays(3)]);
        config()->set('services.twilio', ['account_sid' => 'ACtest', 'auth_token' => 'secret', 'from' => '+15005550006']);
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SMtest'], 201)]);

        $this->actingAs($admin)->post('/admin/reminders/queue')
            ->assertRedirect(route('admin.memberships.index').'#renewal-reminders')
            ->assertSessionHas('status', '1 new reminder(s) queued. 1 submitted to the SMS provider; 0 failed.');
        $this->assertDatabaseHas('sms_reminders', ['user_id' => $member->id, 'status' => 'submitted']);
    }

    public function test_member_can_opt_in_and_opt_out_with_own_phone(): void
    {
        $member = User::factory()->create(['role' => 'member', 'approval_status' => 'approved']);
        $this->actingAs($member)->put('/member/contact', ['phone' => '09171234567', 'sms_consent' => '1'])->assertRedirect();
        $this->assertSame('+639171234567', $member->fresh()->phone);
        $this->assertTrue($member->fresh()->sms_consent);
        $this->put('/member/contact', ['phone' => '09171234567'])->assertRedirect();
        $this->assertFalse($member->fresh()->sms_consent);
    }

    public function test_registration_requires_a_phone_for_sms_consent(): void
    {
        $data = [
            'name' => 'New Member', 'username' => 'newmember',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'sms_consent' => '1',
        ];
        $this->post('/register', $data)->assertSessionHasErrors('phone');
        $this->post('/register', $data + ['phone' => '09171234567'])->assertRedirect('/login');
        $member = User::where('username', 'newmember')->firstOrFail();
        $this->assertSame('+639171234567', $member->phone);
        $this->assertTrue($member->sms_consent);
    }
}
