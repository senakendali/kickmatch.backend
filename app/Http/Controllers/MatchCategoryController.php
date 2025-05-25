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

    public function getByTournament(Request $request)
    {
        $tournamentId = $request->query('tournament_id');

        $query = MatchCategory::query();

        if ($tournamentId) {
            $query->whereIn('id', function ($sub) use ($tournamentId) {
                $sub->select('match_category_id')
                    ->from('tournament_categories')
                    ->where('tournament_id', $tournamentId); // ✅ perbaikan disini
            });
        }

        return response()->json($query->get());
    }


    
}
