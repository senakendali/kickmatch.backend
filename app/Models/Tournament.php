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

   
}
