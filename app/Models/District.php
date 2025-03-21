<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    use HasFactory;
    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function subdistricts()
    {
        return $this->hasMany(Subdistrict::class);
    }

    public function contingents()
    {
        return $this->hasMany(Contingent::class);
    }

    public function teamMembers()
    {
        return $this->hasMany(TeamMember::class);
    }

}
