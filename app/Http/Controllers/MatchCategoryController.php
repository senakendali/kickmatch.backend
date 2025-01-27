<?php

namespace App\Http\Controllers;
use App\Models\MatchCategory;
use Illuminate\Http\Request;

class MatchCategoryController extends Controller
{
    public function index()
    {
        $matchCategories = MatchCategory::all();
        return response()->json($matchCategories);
    }
}
