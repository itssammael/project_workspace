<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TaskBoard extends Model
{
    protected $table = 'task_boards';

    protected $fillable = ['name', 'description', 'status', 'section_id', 'start_date', 'end_date'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'task_board_id');
    }

    public function workflows(): BelongsToMany
    {
        return $this->belongsToMany(Workflow::class, 'task_board_workflow', 'task_board_id', 'workflow_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'task_board_members', 'task_board_id', 'member_id')
            ->withPivot('member_role_id')
            ->withTimestamps();
    }
}
