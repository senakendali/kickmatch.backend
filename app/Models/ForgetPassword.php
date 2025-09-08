<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForgetPassword extends Model
{
    protected $fillable = [
        'id','email','url','status','created_at','updated_at'
    ];

    protected $primaryKey = 'id';
}
