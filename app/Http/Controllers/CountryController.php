<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    public function index()
    {
        $countries = Country::all()->map(function ($country) {
            $country->flag = asset('flags/' . $country->flag);
            return $country;
        });

        return response()->json($countries);
    }
}

