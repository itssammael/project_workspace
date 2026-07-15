<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    protected $fillable = ['user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function memberRoles(): BelongsToMany
    {
        return $this->belongsToMany(MemberRole::class, 'member_member_role');
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'sections_member_pivot');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
