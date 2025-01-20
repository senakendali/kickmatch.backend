<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentActivity extends Model
{
    protected $table = 'tournament_activities';
    protected $guarded = ['created_at', 'updated_at'];
    protected $dates = ['created_at', 'updated_at'];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }
}
