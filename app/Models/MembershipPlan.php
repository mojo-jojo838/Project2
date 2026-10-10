<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipPlan extends Model
{
    protected $fillable = ['name', 'duration_days', 'price'];

    protected function casts(): array
    {
        return ['duration_days' => 'integer', 'price' => 'decimal:2'];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }
}
