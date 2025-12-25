<?php

namespace App\Http\Controllers;
use App\Models\Tournament;
use App\Models\TournamentActivity;
use App\Models\EventOrganizer;

use Illuminate\Http\Request;

class TournamentActivityController extends Controller
{
    public function index(Request $request)
    {
        try {
            // support ?per_page= dan ?perPage=
            $perPage = $request->get('per_page', $request->get('perPage', 10));

            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            // base query
            $query = TournamentActivity::with('tournament');

            // casting dulu biar aman
            $roleId = (int) ($user->role_id ?? 0);

            // === HANYA EO YANG DI-FILTER ===
            // owner / admin / role lain dapat semua data
            if ($roleId === 2) { // 2 = EO (sesuaikan kalau beda)
                // cari EO profile berdasarkan user_id
                $organizerId = EventOrganizer::where('user_id', $user->id)->value('id');

                if ($organizerId) {
                    // hanya activity dari tournament yang organizer_id = organizerId ini
                    $query->whereHas('tournament', function ($q) use ($organizerId) {
                        $q->where('organizer_id', $organizerId);
                    });
                } else {
                    // EO belum punya profile -> hasil kosong tapi tetap bentuk pagination
                    $query->whereRaw('1 = 0');
                }
            }

            $activities = $query->paginate($perPage);

            return response()->json($activities, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Unable to fetch data',
                'message' => $e->getMessage(),
            ], 500);
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
