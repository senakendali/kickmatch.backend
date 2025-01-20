<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentContingent extends Model
{
    protected $table = 'tournament_contingents';
    protected $guarded = ['created_at', 'updated_at'];
    protected $dates = ['created_at', 'updated_at'];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }    

    public function contingent()
    {
        return $this->belongsTo(Contingent::class);
    }
}
