<?php

namespace App\Http\Controllers;

use App\Models\AgeCategory;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AgeCategoryController extends Controller
{
    public function index()
    {
        try {
            $ageCategories = AgeCategory::all();

            return response()->json($ageCategories, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
