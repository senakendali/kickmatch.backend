<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeamStaff extends Model
{
    use SoftDeletes;

    protected $table = 'team_staff';

    protected $fillable = [
        'contingent_id',
        'staff_role_id',
        'full_name',
        'phone',
        'email',
        'is_primary',
        'ordering',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'ordering'   => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */
    public function contingent()
    {
        return $this->belongsTo(Contingent::class);
    }

    public function role()
    {
        return $this->belongsTo(StaffRole::class, 'staff_role_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes (opsional)
    |--------------------------------------------------------------------------
    */
    public function scopePrimary($q)
    {
        return $q->where('is_primary', true);
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('ordering')->orderBy('id');
    }
}
