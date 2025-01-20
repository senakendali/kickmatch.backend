<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $table = 'countries';
    protected $guarded = ['created_at', 'updated_at'];
    protected $dates = ['created_at', 'updated_at'];

    public function contingent()
    {
        return $this->hasMany(Contingent::class);
    }

    public function teamMembers()
    {
        return $this->hasMany(TeamMember::class);
    }

}
