<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberRole extends Model
{
    protected $fillable = ['name', 'slug'];

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
