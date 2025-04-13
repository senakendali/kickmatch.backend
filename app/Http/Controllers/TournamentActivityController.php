<?php

namespace App\Http\Controllers;
use App\Models\Tournament;
use App\Models\TournamentActivity;

use Illuminate\Http\Request;

class TournamentActivityController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Use pagination with a default of 10 items per page
            $categoryClasses = TournamentActivity::with('tournament')->paginate($request->get('per_page', 10));
            return response()->json($categoryClasses, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to fetch data', 'message' => $e->getMessage()], 500);
        }
    }

    // Get a single category class
    public function show($id)
    {
        try {
            $tournamentActivity = TournamentActivity::findOrFail($id);
            return response()->json(['data' => $tournamentActivity], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Category Class not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred', 'message' => $e->getMessage()], 500);
        }
    }

    // Create a new category class
    public function store(Request $request)
    {
        $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        try {
            $tournamentActivity = TournamentActivity::create($request->all());
            return response()->json(['data' => $tournamentActivity], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create category class', 'message' => $e->getMessage()], 500);
        }
    }

    // Update a category class
    public function update(Request $request, $id)
    {
        $request->validate([
            'tournament_id' => 'sometimes|exists:tournaments,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date',
        ]);

        try {
            $tournamentActivity = TournamentActivity::findOrFail($id);
            $tournamentActivity->update($request->all());
            return response()->json(['data' => $tournamentActivity], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Tournament activity not found'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update tournament activity',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    // Delete a category class
    public function destroy($id)
    {
        try {
            $tournamentActivity = TournamentActivity::findOrFail($id);
            $tournamentActivity->delete();
            return response()->json(['message' => 'Category Class deleted successfully'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Category Class not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete category class', 'message' => $e->getMessage()], 500);
        }
    }
}
