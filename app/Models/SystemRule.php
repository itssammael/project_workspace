<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'enabled',
        'status',
        'description',
        'rule_logic',
        'scope',
        'actions',
        'created_by',
        'last_modified_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'rule_logic' => 'array',
        'scope' => 'array',
        'actions' => 'array',
    ];
}
