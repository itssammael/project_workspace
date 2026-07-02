<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleAccess extends Model
{
    protected $table = 'role_access';
    
    protected $fillable = ['role_id', 'permission'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
