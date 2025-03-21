<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contingent extends Model
{
    protected $table = 'contingents';
    protected $guarded = ['created_at', 'updated_at'];
    protected $dates = ['created_at', 'updated_at'];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function teamMembers()
    {
        return $this->hasMany(TeamMember::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function subdistrict()
    {
        return $this->belongsTo(Subdistrict::class);
    }

    public function wards()
    {
        return $this->hasMany(Ward::class);
    }


    public function tournamentContingents()
    {
        return $this->hasMany(TournamentContingent::class);
    }

    
}
