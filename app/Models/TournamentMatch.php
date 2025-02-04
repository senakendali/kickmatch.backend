<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentMatch extends Model
{
    protected $table = 'tournament_matches';
    protected $guarded = ['created_at', 'updated_at'];
    protected $dates = ['created_at', 'updated_at'];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function matchCategory()
    {
        return $this->belongsTo(MatchCategory::class);
    }

    public function ageCategory()
    {
        return $this->belongsTo(AgeCategory::class);
    }

    public function participant1()
    {
        return $this->belongsTo(TeamMember::class, 'team_member_1_id');
    }

    public function participant2()
    {
        return $this->belongsTo(TeamMember::class, 'team_member_2_id');
    }

    public function winner()
    {
        return $this->belongsTo(TeamMember::class, 'winner_id');
    }
}
