<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $fillable = ['name', 'member_id', 'department_id'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'sections_member_pivot');
    }

    public function taskBoards(): HasMany
    {
        return $this->hasMany(TaskBoard::class, 'section_id');
    }

    public function projects(): HasMany
    {
        return $this->taskBoards();
    }
}
