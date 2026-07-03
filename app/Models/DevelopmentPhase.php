<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevelopmentPhase extends Model
{
    protected $fillable = ['name', 'order', 'development_type_id'];

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function developmentType(): BelongsTo
    {
        return $this->belongsTo(DevelopmentType::class, 'development_type_id');
    }
}
