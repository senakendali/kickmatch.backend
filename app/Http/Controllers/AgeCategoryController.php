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

    // AgeCategoryController.php
    public function getByTournament(Request $request)
    {
        $tournamentId = $request->query('tournament_id');

        $query = AgeCategory::query();

        if ($tournamentId) {
            $query->whereIn('id', function ($sub) use ($tournamentId) {
                $sub->select('age_category_id')
                    ->from('tournament_age_categories')
                    ->where('tournament_id', $tournamentId);
            });
        }

        return response()->json($query->get());
    }

}
