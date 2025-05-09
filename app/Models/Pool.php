<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pool extends Model
{
    protected $table = 'pools';
    protected $guarded = ['created_at', 'updated_at'];
    protected $dates = ['created_at', 'updated_at'];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function matchCategory()
    {
        return $this->belongsTo(MatchCategory::class);
    }

    public function ageCategory()
    {
        return $this->belongsTo(AgeCategory::class);
    }

    public function categoryClass()
    {
        return $this->belongsTo(CategoryClass::class, 'category_class_id');
    }


    public function matches()
    {
        return $this->hasMany(TournamentMatch::class);
    }

    public function seniMatches()
    {
        return $this->hasMany(SeniMatch::class);
    }


    
}
