<?php

namespace Spatie\Permission\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_role', 'role_id', 'user_group_id');
    }
}


