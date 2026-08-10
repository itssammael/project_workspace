<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'short_name',
        'department_head_id',
    ];

    public function departmentHead(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'department_head_id');
    }

    public function sections()
    {
        return $this->hasMany(Section::class);
    }
}
