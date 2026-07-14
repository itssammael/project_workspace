<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Project extends Model
{
    protected $fillable = ['name', 'description', 'status', 'team_id', 'start_date', 'end_date'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function developmentPhases(): BelongsToMany
    {
        return $this->belongsToMany(DevelopmentPhase::class, 'project_development_phase');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'project_members')
            ->withPivot('member_role_id')
            ->withTimestamps();
    }
}
