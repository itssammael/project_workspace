<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = ['name', 'slug', 'show_role_switcher'];

    protected $casts = [
        'show_role_switcher' => 'boolean',
    ];

    public function permissions(): HasMany
    {
        return $this->hasMany(RoleAccess::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
