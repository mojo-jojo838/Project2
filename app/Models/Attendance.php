<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Attendance extends Model
{
    protected $table = 'attendance';
    protected $fillable = ['user_id', 'walk_in_name', 'method', 'check_in_date', 'checked_in_at', 'checked_out_at', 'auto_checked_out'];
    protected function casts(): array { return ['check_in_date' => 'date', 'checked_in_at' => 'datetime', 'checked_out_at' => 'datetime', 'auto_checked_out' => 'boolean']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class)->withTrashed(); }
    public function payment(): HasOne { return $this->hasOne(Payment::class); }
}
