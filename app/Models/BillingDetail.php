<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingDetail extends Model
{
    protected $table = 'billing_details';
    protected $guarded = ['created_at', 'updated_at'];
    protected $dates = ['created_at', 'updated_at'];

    public function billing()
    {
        return $this->belongsTo(Billing::class);
    }

    public function tournamentCategory()
    {
        return $this->belongsTo(TournamentCategory::class);
    }

    public function teamMember()
    {
        return $this->belongsTo(TeamMember::class);
    }
}
