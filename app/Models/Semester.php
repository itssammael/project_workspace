<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Semester extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'month_start', 'month_end'];

    public function scheduledActivities(): HasMany
    {
        return $this->hasMany(ScheduledActivity::class);
    }

    public function tardinessAbsencesUndertimes(): HasMany
    {
        return $this->hasMany(TardinessAbsenceUndertime::class);
    }
}
