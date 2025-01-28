<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChampionshipCategory extends Model
{
    protected $table = 'championship_categories';
    protected $guarded = ['created_at', 'updated_at'];
    protected $dates = ['created_at', 'updated_at'];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function teamMembers()
    {
        return $this->hasMany(TeamMember::class);
    }
}
