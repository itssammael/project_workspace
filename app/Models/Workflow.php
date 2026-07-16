<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Workflow extends Model
{
    protected $table = 'workflows';

    protected $fillable = ['name', 'order', 'workflow_type_id'];

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'workflow_id');
    }

    public function workflowType(): BelongsTo
    {
        return $this->belongsTo(WorkflowType::class, 'workflow_type_id');
    }
}
