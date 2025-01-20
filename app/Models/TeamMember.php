<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamMember extends Model
{
    protected $table = 'team_members';
    protected $guarded = ['created_at', 'updated_at'];
    protected $dates = ['created_at', 'updated_at'];

    public function contingent()
    {
        return $this->belongsTo(Contingent::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }
}


