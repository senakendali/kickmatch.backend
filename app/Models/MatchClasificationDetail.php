<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchClasificationDetail extends Model
{
    protected $table = 'match_clasification_details';
    protected $guarded = ['created_at', 'updated_at'];
    protected $dates = ['created_at', 'updated_at'];

    public function matchClasification()
    {
        return $this->belongsTo(MatchClasification::class);
    }

    public function categoryClass()
    {
        return $this->belongsTo(CategoryClass::class);
    }

}
