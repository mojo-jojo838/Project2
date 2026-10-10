<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_member_can_check_in_and_out_multiple_times_with_actual_scan_times(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-30 09:01:02'));
        $admin = User::factory()->create(['role' => 'admin', 'approval_status' => 'approved']);
        $member = User::factory()->create(['role' => 'member', 'approval_status' => 'approved', 'qr_token' => 'test-token']);
        $plan = MembershipPlan::create(['name' => 'Monthly', 'duration_days' => 30, 'price' => 1200]);
        Membership::create(['user_id' => $member->id, 'membership_plan_id' => $plan->id, 'starts_at' => today(), 'expires_at' => today()->addDays(29)]);

        $this->actingAs($member)->get('/member/qr-code')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->actingAs($admin)->postJson('/admin/attendance/check-in', ['qr_token' => 'DG:test-token'])
            ->assertOk()->assertJsonPath('action', 'check-in')
            ->assertJsonPath('record.checked_in_at', 'Sep 30, 2026 · 9:01 AM');
        $this->assertDatabaseHas('attendance', ['user_id' => $member->id, 'method' => 'qr']);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-30 10:15:20'));
        $this->postJson('/admin/attendance/check-in', ['qr_token' => 'DG:test-token'])
            ->assertOk()->assertJsonPath('action', 'check-out')
            ->assertJsonPath('record.checked_out_at', 'Sep 30, 2026 · 10:15 AM');
        $this->assertNotNull(Attendance::firstOrFail()->checked_out_at);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-30 14:30:45'));
        $this->postJson('/admin/attendance/check-in', ['qr_token' => 'DG:test-token'])
            ->assertOk()->assertJsonPath('action', 'check-in')
            ->assertJsonPath('record.checked_in_at', 'Sep 30, 2026 · 2:30 PM');
        $this->assertSame(2, Attendance::count());
        $this->actingAs($member)->get('/member/dashboard')->assertSee('Attendance history');
    }

    public function test_inactive_member_is_rejected_and_walk_in_is_recorded(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'approval_status' => 'approved']);
        $member = User::factory()->create(['role' => 'member', 'approval_status' => 'approved', 'qr_token' => 'inactive-token']);
        $this->actingAs($admin)->post('/admin/attendance/check-in', ['qr_token' => 'DG:inactive-token'])->assertSessionHasErrors('qr_token');
        $this->post('/admin/attendance/walk-in', ['walk_in_name' => 'Guest Visitor'])->assertRedirect();
        $this->assertDatabaseHas('attendance', ['walk_in_name' => 'Guest Visitor', 'method' => 'walk-in']);
        $this->actingAs($member)->post('/admin/attendance/walk-in', ['walk_in_name' => 'Another Guest'])->assertForbidden();
        $this->get('/admin/attendance')->assertForbidden();
    }

    public function test_scanner_explains_missing_and_expired_memberships(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'approval_status' => 'approved']);
        $member = User::factory()->create(['role' => 'member', 'approval_status' => 'approved', 'qr_token' => 'membership-check']);

        $this->actingAs($admin)->postJson('/admin/attendance/check-in', ['qr_token' => 'DG:membership-check'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.qr_token.0', 'This member has no membership subscription yet.');

        $plan = MembershipPlan::where('name', 'Student')->firstOrFail();
        Membership::create([
            'user_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'starts_at' => today()->subDays(30),
            'expires_at' => today()->subDay(),
        ]);
        $this->postJson('/admin/attendance/check-in', ['qr_token' => 'DG:membership-check'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.qr_token.0', 'This member’s membership expired on '.today()->subDay()->format('M j, Y').'.');
    }

    public function test_member_can_check_out_after_midnight_using_the_open_visit(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-30 23:59:50'));
        $admin = User::factory()->create(['role' => 'admin', 'approval_status' => 'approved']);
        $member = User::factory()->create(['role' => 'member', 'approval_status' => 'approved', 'qr_token' => 'midnight-token']);
        $plan = MembershipPlan::where('name', 'Student')->firstOrFail();
        Membership::create(['user_id' => $member->id, 'membership_plan_id' => $plan->id, 'starts_at' => today(), 'expires_at' => today()]);

        $this->actingAs($admin)->postJson('/admin/attendance/check-in', ['qr_token' => 'DG:midnight-token'])
            ->assertOk()->assertJsonPath('action', 'check-in');
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-01 00:00:10'));
        $this->postJson('/admin/attendance/check-in', ['qr_token' => 'DG:midnight-token'])
            ->assertOk()->assertJsonPath('action', 'check-out')
            ->assertJsonPath('record.checked_out_at', 'Oct 1, 2026 · 12:00 AM');
        $this->assertSame('2026-09-30', Attendance::firstOrFail()->check_in_date->toDateString());
    }

    public function test_admin_can_filter_attendance_by_type_and_date(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'approval_status' => 'approved']);
        $member = User::factory()->create(['name' => 'Member Today', 'role' => 'member', 'approval_status' => 'approved']);
        Attendance::create(['user_id' => $member->id, 'method' => 'qr', 'check_in_date' => today(), 'checked_in_at' => now()]);
        Attendance::create(['walk_in_name' => 'Guest Yesterday', 'method' => 'walk-in', 'check_in_date' => today()->subDay(), 'checked_in_at' => now()->subDay()]);

        $this->actingAs($admin)->get('/admin/attendance')
            ->assertOk()->assertSee('Member Today')->assertDontSee('Guest Yesterday');
        $this->get('/admin/attendance?type=walk-in&period=all')
            ->assertOk()->assertSee('Guest Yesterday')->assertDontSee('Member Today');
        $this->get('/admin/attendance?type=qr&period=all')
            ->assertOk()->assertSee('Member Today')->assertDontSee('Guest Yesterday');
        $this->get('/admin/attendance?period=custom&date='.today()->subDay()->toDateString())
            ->assertOk()->assertSee('Guest Yesterday')->assertDontSee('Member Today');
    }

    public function test_open_member_visit_is_closed_at_ten_pm_even_when_command_runs_late(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-01 21:00:00'));
        $member = User::factory()->create(['role' => 'member', 'approval_status' => 'approved']);
        $visit = Attendance::create([
            'user_id' => $member->id, 'method' => 'qr',
            'check_in_date' => today(), 'checked_in_at' => now(),
        ]);

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-01 21:59:00'));
        $this->artisan('attendance:close-overdue')->assertSuccessful();
        $this->assertNull($visit->fresh()->checked_out_at);

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-01 22:07:00'));
        $this->artisan('attendance:close-overdue')->assertSuccessful();
        $this->assertSame('2026-10-01 22:00:00', $visit->fresh()->checked_out_at->format('Y-m-d H:i:s'));
        $this->assertTrue($visit->fresh()->auto_checked_out);
        $this->artisan('attendance:close-overdue')->assertSuccessful();
        $this->assertSame(1, Attendance::where('auto_checked_out', true)->count());
    }

    public function test_late_check_in_remains_open_until_the_next_day_ten_pm(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-01 22:30:00'));
        $member = User::factory()->create(['role' => 'member', 'approval_status' => 'approved']);
        $visit = Attendance::create([
            'user_id' => $member->id, 'method' => 'qr',
            'check_in_date' => today(), 'checked_in_at' => now(),
        ]);

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-02 21:59:00'));
        $this->artisan('attendance:close-overdue')->assertSuccessful();
        $this->assertNull($visit->fresh()->checked_out_at);

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-02 22:01:00'));
        $this->artisan('attendance:close-overdue')->assertSuccessful();
        $this->assertSame('2026-10-02 22:00:00', $visit->fresh()->checked_out_at->format('Y-m-d H:i:s'));
    }

    public function test_scan_after_cutoff_records_the_scheduled_checkout_time(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-01 20:00:00'));
        $admin = User::factory()->create(['role' => 'admin', 'approval_status' => 'approved']);
        $member = User::factory()->create(['role' => 'member', 'approval_status' => 'approved', 'qr_token' => 'auto-token']);
        Attendance::create([
            'user_id' => $member->id, 'method' => 'qr',
            'check_in_date' => today(), 'checked_in_at' => now(),
        ]);

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-01 22:05:00'));
        $this->actingAs($admin)->postJson('/admin/attendance/check-in', ['qr_token' => 'DG:auto-token'])
            ->assertOk()->assertJsonPath('action', 'check-out')
            ->assertJsonPath('record.checked_out_at', 'Oct 1, 2026 · 10:00 PM')
            ->assertJsonPath('record.auto_checked_out', true);
    }
}
