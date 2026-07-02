<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    protected $fillable = ['user_id', 'member_role_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function memberRole(): BelongsTo
    {
        return $this->belongsTo(MemberRole::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'teams_member_pivot');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
