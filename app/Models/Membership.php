<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Membership extends Model
{
    protected $fillable = ['user_id', 'membership_plan_id', 'starts_at', 'expires_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'date', 'expires_at' => 'date'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class)->withTrashed(); }
    public function plan(): BelongsTo { return $this->belongsTo(MembershipPlan::class, 'membership_plan_id'); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }

    public function getStatusAttribute(): string
    {
        if ($this->starts_at->isFuture()) return 'Upcoming';
        if ($this->expires_at->isBefore(today())) return 'Expired';
        return $this->days_remaining <= 3 ? 'Expiring' : 'Active';
    }

    public function getDaysRemainingAttribute(): int
    {
        if ($this->expires_at->isBefore(today())) return 0;
        return (int) today()->diffInDays($this->expires_at);
    }
}
