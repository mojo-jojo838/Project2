<?php

namespace Tests\Feature;

use App\Models\Membership;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MembershipStatusTest extends TestCase
{
    public function test_membership_becomes_expiring_in_its_last_three_days(): void
    {
        Carbon::setTestNow('2026-09-30 10:00:00');

        try {
            $membership = new Membership(['starts_at' => '2026-09-01', 'expires_at' => '2026-10-04']);
            $this->assertSame('Active', $membership->status);
            $this->assertSame(4, $membership->days_remaining);

            $membership->expires_at = '2026-10-03';
            $this->assertSame('Expiring', $membership->status);
            $this->assertSame(3, $membership->days_remaining);

            $membership->expires_at = '2026-09-30';
            $this->assertSame('Expiring', $membership->status);
            $this->assertSame(0, $membership->days_remaining);

            $membership->expires_at = '2026-09-29';
            $this->assertSame('Expired', $membership->status);
            $this->assertSame(0, $membership->days_remaining);
        } finally {
            Carbon::setTestNow();
        }
    }
}
