<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowType extends Model
{
    use HasFactory;

    protected $table = 'workflow_types';

    protected $fillable = ['name'];

    public function workflows(): HasMany
    {
        return $this->hasMany(Workflow::class, 'workflow_type_id');
    }
}
