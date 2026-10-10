<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsReminder extends Model
{
    protected $fillable = ['user_id', 'membership_id', 'phone', 'message', 'scheduled_for', 'status', 'provider_sid', 'error', 'submitted_at'];
    protected function casts(): array { return ['scheduled_for' => 'date', 'submitted_at' => 'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class)->withTrashed(); }
    public function membership(): BelongsTo { return $this->belongsTo(Membership::class); }
}
