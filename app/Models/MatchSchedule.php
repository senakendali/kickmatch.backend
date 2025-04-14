<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'tournament_arena_id',
        'scheduled_date',
        'start_time',
        'end_time',
        'note',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function arena()
    {
        return $this->belongsTo(TournamentArena::class, 'tournament_arena_id');
    }

    public function details()
    {
        return $this->hasMany(MatchScheduleDetail::class);
    }
}
