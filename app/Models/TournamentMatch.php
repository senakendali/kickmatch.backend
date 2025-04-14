<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'pool_id',
        'round',
        'match_number',
        'participant_1',
        'participant_2',
        'winner_id',
        'next_match_id'
    ];

    // Relasi ke Pool
    public function pool()
    {
        return $this->belongsTo(Pool::class);
    }

    // Relasi ke Peserta 1
    public function participantOne()
    {
        return $this->belongsTo(TeamMember::class, 'participant_1');
    }

    // Relasi ke Peserta 2
    public function participantTwo()
    {
        return $this->belongsTo(TeamMember::class, 'participant_2');
    }

    // Relasi ke Pemenang
    public function winner()
    {
        return $this->belongsTo(TeamMember::class, 'winner_id');
    }

    // Relasi ke Pertandingan Selanjutnya
    public function nextMatch()
    {
        return $this->belongsTo(TournamentMatch::class, 'next_match_id');
    }

    
    public function scheduleDetails()
    {
        return $this->hasMany(MatchScheduleDetail::class);
    }

}

