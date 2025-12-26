<?php

// app/Models/TournamentDrawing.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentDrawing extends Model
{
    protected $fillable = [
        'tournament_id',
        'name',
        'description',
        'format',
        'group_size',
        'is_active',
        'config',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'array',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }
}
