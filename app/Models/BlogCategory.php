<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogCategory extends Model
{
    protected $table = 'blog_categories';

    protected $fillable = [
        'name', 'slug', 'description', 'parent_id', 'is_active', 'ordering',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(BlogCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(BlogCategory::class, 'parent_id');
    }

    public function posts()
    {
        return $this->belongsToMany(BlogPost::class, 'blog_post_category', 'category_id', 'post_id');
    }
}
