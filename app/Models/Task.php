<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = [
        'name',
        'details',
        'deliverables',
        'duration',
        'development_phase_id',
        'member_id',
        'project_id',
        'start_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function developmentPhase(): BelongsTo
    {
        return $this->belongsTo(DevelopmentPhase::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
