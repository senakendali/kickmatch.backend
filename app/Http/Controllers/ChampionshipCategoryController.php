<?php

namespace App\Http\Controllers;

use App\Models\ChampionshipCategory;
use Illuminate\Http\Request;

class ChampionshipCategoryController extends Controller
{
    public function index()
    {
        try {
            $championshipCategories = ChampionshipCategory::all();
            return response()->json($championshipCategories, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to fetch data', 'message' => $e->getMessage()], 500);
        }
    }
}
