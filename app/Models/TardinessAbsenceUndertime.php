<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TardinessAbsenceUndertime extends Model
{
    use HasFactory;

    protected $table = 'tardiness_absences_undertimes';

    protected $fillable = [
        'member_id',
        'tardy',
        'absences',
        'undertime',
        'month',
        'year',
        'semester_id',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }
}
