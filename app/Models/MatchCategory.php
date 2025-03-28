<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchCategory extends Model
{
    protected $table = 'match_categories';
    protected $guarded = ['created_at', 'updated_at'];
    protected $dates = ['created_at', 'updated_at'];

    public function tournamentCategories()
    {
        return $this->hasMany(TournamentCategory::class);
    }

   public function matchClasifications()
    {
        return $this->hasMany(MatchClasification::class);
    }

    public function teamMembers()
    {
        return $this->hasMany(TeamMember::class);
    }

    public function pools()
    {
        return $this->hasMany(Pool::class);
    }
}
