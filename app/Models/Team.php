<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    protected $fillable = ['name', 'member_id'];

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'teams_member_pivot');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
