<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubTaskComment extends Model
{
    protected $table = 'sub_task_comments';

    protected $fillable = [
        'sub_task_id',
        'member_id',
        'comment',
    ];

    public function subTask(): BelongsTo
    {
        return $this->belongsTo(SubTask::class, 'sub_task_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }
}
