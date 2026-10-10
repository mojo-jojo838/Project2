<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AutomaticCheckout
{
    public function closeOverdue(?Carbon $at = null): int
    {
        $at ??= now();
        $closed = 0;

        Attendance::where('method', 'qr')->whereNotNull('user_id')->whereNull('checked_out_at')
            ->where('checked_in_at', '<=', $at)
            ->select(['id', 'user_id'])->chunkById(100, function ($visits) use ($at, &$closed) {
                foreach ($visits as $openVisit) DB::transaction(function () use ($openVisit, $at, &$closed) {
                    $member = User::withTrashed()->whereKey($openVisit->user_id)->lockForUpdate()->first();
                    if (! $member) return;
                    $visit = Attendance::whereKey($openVisit->id)->whereNull('checked_out_at')->lockForUpdate()->first();
                    if ($visit && $this->closeVisitIfOverdue($visit, $at)) $closed++;
                });
            });

        return $closed;
    }

    public function closeVisitIfOverdue(Attendance $visit, Carbon $at): bool
    {
        $cutoff = $visit->checked_in_at->copy()->setTime(22, 0);
        if ($visit->checked_in_at->greaterThanOrEqualTo($cutoff)) $cutoff->addDay();
        if ($at->lessThan($cutoff) || $visit->checked_out_at !== null) return false;

        $visit->update(['checked_out_at' => $cutoff, 'auto_checked_out' => true]);
        return true;
    }
}
