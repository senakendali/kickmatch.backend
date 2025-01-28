<?php

namespace App\Http\Controllers;
use App\Models\MatchCategory;
use Illuminate\Http\Request;

class MatchCategoryController extends Controller
{
    public function index(Request $request)
    {
        $camphionshipId = $request->query('championship_category_id'); 

        if ($camphionshipId) {
            $matchCategories = MatchCategory::where('championship_category_id', $camphionshipId)->get();
        } else {
            $matchCategories = MatchCategory::all();
        }

        return response()->json($matchCategories);
    }

    
}
