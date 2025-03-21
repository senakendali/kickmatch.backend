<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Province extends Model
{
    use HasFactory;
    public function district()
    {
        return $this->hasMany(District::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function teamMembers()
    {
        return $this->hasMany(TeamMember::class);
    }

    public function contingents()
    {
        return $this->hasMany(Contingent::class);
    }
}
