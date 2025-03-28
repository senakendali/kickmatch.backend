<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tournament extends Model
{
    protected $table = 'tournaments';
    protected $guarded = ['created_at', 'updated_at'];
    protected $dates = ['created_at', 'updated_at'];

    public function tournamentActivities()
    {
        return $this->hasMany(TournamentActivity::class);
    }

    public function tournamentCategories()
    {
        return $this->hasMany(TournamentCategory::class);
    }

    public function tournamentAgeCategories()
    {
        return $this->hasMany(TournamentAgeCategory::class);
    }

    public function tournamentContingents()
    {
        return $this->hasMany(TournamentContingent::class);
    }

    public function tournamentContactPersons()
    {
        return $this->hasMany(TournamentContactPerson::class);
    }

    public function billings()
    {
        return $this->hasMany(Billing::class);
    }

    public function championshipCategories()
    {
        return $this->hasMany(ChampionshipCategory::class);
    }

    public function participants()
    {
        return $this->hasMany(TournamentParticipant::class);
    }

    public function pools()
    {
        return $this->hasMany(Pool::class);
    }

   
}
