<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchScheduleDetail extends Model
{
    protected $fillable = [
        'match_schedule_id',
        'match_category_id',
        'tournament_match_id',
        'seni_match_id',
        'order',
        'start_time',
        'note',
    ];

    public function matchSchedule()
    {
        return $this->belongsTo(MatchSchedule::class);
    }

    public function tournamentMatch()
    {
        return $this->belongsTo(TournamentMatch::class);
    }

    public function schedule()
    {
        return $this->belongsTo(MatchSchedule::class, 'match_schedule_id');
    }

    public function seniMatch()
    {
        return $this->belongsTo(\App\Models\SeniMatch::class, 'seni_match_id');
    }


}
