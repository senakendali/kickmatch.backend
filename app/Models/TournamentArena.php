<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentArena extends Model
{
    protected $table = 'tournament_arena';
    protected $guarded = ['created_at', 'updated_at'];
    protected $dates = ['created_at', 'updated_at'];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    } 
    
}
