<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentCategory extends Model
{
    protected $table = 'tournament_categories';
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
}
