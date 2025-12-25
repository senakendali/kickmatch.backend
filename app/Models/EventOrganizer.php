<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventOrganizer extends Model
{
    protected $table = 'event_organizers';

    protected $fillable = [
        'user_id',
        'organizer_name','brand_name','organizer_type','phone_whatsapp','email',
        'province_id','district_id','province','city','country','address',
        'website','instagram','logo',
        'pic_name','pic_position','pic_phone','pic_email',
        'legal_name','legal_document_type','id_number','npwp_number','nib_number','business_address',
        'bank_name','bank_account_name','bank_account_number','billing_email',
        'onboarding_completed','verification_status','submitted_at','verified_at',
    ];

    protected $casts = [
        'onboarding_completed' => 'boolean',
        'submitted_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tournaments()
    {
        return $this->hasMany(Tournament::class, 'organizer_id'); 
    }


}
