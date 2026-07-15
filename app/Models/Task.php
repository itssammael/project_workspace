<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = [
        'name',
        'details',
        'development_phase_id',
        'project_id',
    ];

    protected $casts = [];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function developmentPhase(): BelongsTo
    {
        return $this->belongsTo(DevelopmentPhase::class);
    }

    public function subTasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SubTask::class);
    }
}
