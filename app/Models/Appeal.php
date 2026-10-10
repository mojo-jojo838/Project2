<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appeal extends Model
{
    protected $fillable = ['user_id', 'username', 'message', 'status', 'admin_note', 'reviewed_at', 'reset_code_hash', 'reset_expires_at', 'reset_used_at'];

    protected function casts(): array { return ['reviewed_at' => 'datetime', 'reset_expires_at' => 'datetime', 'reset_used_at' => 'datetime']; }

    public function user(): BelongsTo { return $this->belongsTo(User::class)->withTrashed(); }
}
