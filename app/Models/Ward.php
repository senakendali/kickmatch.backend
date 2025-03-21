<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ward extends Model
{
    use HasFactory;
    public function subdistrict()
    {
        return $this->belongsTo(Subdistrict::class);
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
