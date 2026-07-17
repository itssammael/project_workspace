<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubTaskAttachment extends Model
{
    protected $table = 'sub_task_attachments';

    protected $fillable = [
        'sub_task_id',
        'attachment_type',
        'attachment',
    ];

    public function subTask(): BelongsTo
    {
        return $this->belongsTo(SubTask::class, 'sub_task_id');
    }
}
