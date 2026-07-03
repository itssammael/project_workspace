<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DevelopmentType extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function developmentPhases(): HasMany
    {
        return $this->hasMany(DevelopmentPhase::class, 'development_type_id');
    }
}
