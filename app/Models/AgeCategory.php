<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgeCategory extends Model
{
    protected $table = 'age_categories';
    protected $guarded = ['created_at', 'updated_at'];
    protected $dates = ['created_at', 'updated_at'];

    public function tournamentAgeCategories()
    {
        return $this->hasMany(TournamentAgeCategory::class);
    }
}
