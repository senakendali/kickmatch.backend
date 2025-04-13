<?php

namespace App\Http\Controllers;

use App\Models\TournamentArena;
use Illuminate\Http\Request;

class TournamentArenaController extends Controller
{
    public function index(Request $request)
    {
        try {
            $arenas = TournamentArena::with('tournament')->paginate($request->get('per_page', 10));
            return response()->json($arenas, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to fetch data', 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $arena = TournamentArena::with('tournament')->findOrFail($id);
            return response()->json(['data' => $arena], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Tournament Arena not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred', 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'name' => 'required|string|max:255',
        ]);

        try {
            $arena = TournamentArena::create($request->all());
            return response()->json(['data' => $arena], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create Tournament Arena', 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'tournament_id' => 'sometimes|exists:tournaments,id',
            'name' => 'sometimes|string|max:255',
        ]);

        try {
            $arena = TournamentArena::findOrFail($id);
            $arena->update($request->all());
            return response()->json(['data' => $arena], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Tournament Arena not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update Tournament Arena', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $arena = TournamentArena::findOrFail($id);
            $arena->delete();
            return response()->json(['message' => 'Tournament Arena deleted successfully'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Tournament Arena not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete Tournament Arena', 'message' => $e->getMessage()], 500);
        }
    }
}
