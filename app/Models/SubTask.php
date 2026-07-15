<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubTask extends Model
{
    protected $table = 'sub_tasks';

    protected $fillable = [
        'name',
        'details',
        'deliverables',
        'duration',
        'task_id',
        'member_id',
        'start_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
