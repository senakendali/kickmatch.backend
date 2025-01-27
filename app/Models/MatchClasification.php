<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchClasification extends Model
{
    protected $table = 'match_clasifications';
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

    public function matchClasificationDetails()
    {
        return $this->hasMany(MatchClasificationDetail::class);
    }
}
