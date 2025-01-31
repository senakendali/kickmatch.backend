<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamMember extends Model
{
    protected $table = 'team_members';
    protected $guarded = ['created_at', 'updated_at'];
    protected $dates = ['created_at', 'updated_at'];

    public function contingent()
    {
        return $this->belongsTo(Contingent::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function subdistrict()
    {
        return $this->belongsTo(Subdistrict::class);
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    public function ageCategory()
    {
        return $this->belongsTo(AgeCategory::class);
    }

    public function categoryClass()
    {
        return $this->belongsTo(CategoryClass::class);
    }

    public function championshipCategory()
    {
        return $this->belongsTo(ChampionshipCategory::class);
    }

    public function matchCategory()
    {
        return $this->belongsTo(MatchCategory::class);
    }

    public function billingDetails()
    {
        return $this->hasMany(BillingDetail::class);
    }
    
}


