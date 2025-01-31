<?php

namespace Spatie\Permission\Models;
use Spatie\Permission\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    use HasPermissions;
    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_role', 'role_id', 'user_group_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }
}


