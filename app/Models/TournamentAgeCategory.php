<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentAgeCategory extends Model
{
    protected $table = 'tournament_age_categories';
    protected $guarded = ['created_at', 'updated_at'];
    protected $dates = ['created_at', 'updated_at'];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    } 
    
    public function ageCategory()
    {
        return $this->belongsTo(AgeCategory::class);
    }

    


}
