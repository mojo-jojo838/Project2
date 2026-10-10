<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = ['user_id', 'membership_id', 'attendance_id', 'amount', 'paid_at', 'method', 'reference', 'notes', 'status'];

    protected function casts(): array
    {
        return ['paid_at' => 'date', 'amount' => 'decimal:2'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class)->withTrashed(); }
    public function membership(): BelongsTo { return $this->belongsTo(Membership::class); }
    public function attendance(): BelongsTo { return $this->belongsTo(Attendance::class); }
}
