<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_membership_and_renewal_record_fixed_payments_received(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'approval_status' => 'approved']);
        $member = User::factory()->create(['role' => 'member', 'approval_status' => 'approved']);
        $student = MembershipPlan::where('name', 'Student')->firstOrFail();
        $regular = MembershipPlan::where('name', 'Regular')->firstOrFail();

        $this->actingAs($admin)->post('/admin/memberships', [
            'user_id' => $member->id, 'membership_plan_id' => $student->id,
            'starts_at' => today()->toDateString(),
        ])->assertRedirect();
        $this->post('/admin/memberships', [
            'user_id' => $member->id, 'membership_plan_id' => $regular->id,
            'starts_at' => today()->addDays(30)->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('payments', ['user_id' => $member->id, 'amount' => '900.00', 'status' => 'paid', 'method' => 'automatic']);
        $this->assertDatabaseHas('payments', ['user_id' => $member->id, 'amount' => '950.00', 'status' => 'paid', 'method' => 'automatic']);
        $this->assertTrue(Payment::where('user_id', $member->id)->get()->every(fn ($payment) => $payment->paid_at?->isToday()));
        $this->assertSame(2, Payment::where('user_id', $member->id)->count());
        $this->get('/admin/payments')->assertOk()->assertSee('1,850.00')->assertDontSee('Mark received')->assertDontSee('AMOUNT DUE');
        $this->actingAs($member)->get('/member/dashboard')->assertSee('900.00')->assertSee('950.00')->assertSee('Received');
    }

    public function test_walk_in_records_120_received_and_period_totals(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-30 10:00:00'));
        $admin = User::factory()->create(['role' => 'admin', 'approval_status' => 'approved']);
        $this->actingAs($admin)->post('/admin/attendance/walk-in', ['walk_in_name' => 'Guest Visitor'])->assertRedirect();
        $walkIn = Payment::with('attendance')->firstOrFail();
        $this->assertSame('120.00', $walkIn->amount);
        $this->assertSame('paid', $walkIn->status);
        $this->assertSame('2026-09-30', $walkIn->paid_at->toDateString());
        $this->assertSame('Guest Visitor', $walkIn->attendance->walk_in_name);

        foreach ([['100.00', '2026-09-28'], ['200.00', '2026-09-01'], ['300.00', '2026-08-31']] as [$amount, $date]) {
            Payment::create(['amount' => $amount, 'status' => 'paid', 'paid_at' => $date, 'method' => 'automatic']);
        }
        $this->get('/admin/payments')->assertOk()
            ->assertSee('COLLECTED TODAY')->assertSee('₱120.00')
            ->assertSee('COLLECTED THIS WEEK')->assertSee('₱220.00')
            ->assertSee('COLLECTED THIS MONTH')->assertSee('₱420.00')
            ->assertSee('COLLECTED THIS YEAR')->assertSee('₱720.00');
        $this->get('/admin/payments?date=2026-09-28')->assertOk()
            ->assertSee('Collected on September 28, 2026:')->assertSee('₱100.00');
    }

    public function test_paid_memberships_are_preserved_and_manual_payment_route_is_removed(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'approval_status' => 'approved']);
        $member = User::factory()->create(['role' => 'member', 'approval_status' => 'approved']);
        $plan = MembershipPlan::where('name', 'Student')->firstOrFail();
        $membership = Membership::create(['user_id' => $member->id, 'membership_plan_id' => $plan->id, 'starts_at' => today()->subDays(60), 'expires_at' => today()->subDays(31)]);
        $payment = Payment::create(['user_id' => $member->id, 'membership_id' => $membership->id, 'amount' => '900.00', 'status' => 'paid', 'paid_at' => today(), 'method' => 'automatic']);
        $this->actingAs($member)->post('/admin/payments/'.$payment->id.'/mark-paid')->assertNotFound();
        $this->actingAs($admin)->delete('/admin/memberships/'.$membership->id)->assertSessionHasErrors('membership');
        $this->assertDatabaseHas('memberships', ['id' => $membership->id]);
    }
}
