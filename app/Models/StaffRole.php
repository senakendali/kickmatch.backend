<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffRole extends Model
{
    protected $fillable = ['name', 'slug'];

    public function teamStaff()
    {
        return $this->hasMany(TeamStaff::class);
    }

}
