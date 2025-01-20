<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryClass extends Model
{
    protected $table = 'category_classes';
    protected $guarded = ['created_at', 'updated_at'];
    protected $dates = ['created_at', 'updated_at'];

    public function ageCategory()
    {
        return $this->belongsTo(AgeCategory::class);
    }
}
