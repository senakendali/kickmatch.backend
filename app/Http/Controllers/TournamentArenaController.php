<?php

namespace App\Http\Controllers;

use App\Models\TournamentArena;
use App\Models\EventOrganizer;
use Illuminate\Http\Request;

class TournamentArenaController extends Controller
{
    public function index(Request $request)
    {
        try {
            // support ?per_page= dan ?perPage=
            $perPage = $request->get('per_page', $request->get('perPage', 10));
            $search  = $request->input('search', '');

            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            // base query
            $query = TournamentArena::with('tournament');

            // casting dulu biar aman
            $roleId = (int) ($user->role_id ?? 0);

            // === HANYA EO YANG DI-FILTER ===
            // owner / admin / role lain dapat semua data
            if ($roleId === 2) { // 2 = EO
                // cari EO profile berdasar user_id
                $organizerId = EventOrganizer::where('user_id', $user->id)->value('id');

                if ($organizerId) {
                    // hanya arena dari tournament yang organizer_id = EO ini
                    $query->whereHas('tournament', function ($q) use ($organizerId) {
                        $q->where('organizer_id', $organizerId);
                    });
                } else {
                    // EO belum punya profile -> balikin kosong tapi tetap berbentuk pagination
                    $query->whereRaw('1 = 0');
                }
            }

            // optional: search by arena name / tournament name
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhereHas('tournament', function ($sub) use ($search) {
                          $sub->where('name', 'like', '%' . $search . '%');
                      });
                });
            }

            $arenas = $query->paginate($perPage);

            return response()->json($arenas, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Unable to fetch data',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function getByTournament($tournamentId)
    {
        try {
            $arenas = TournamentArena::where('tournament_id', $tournamentId)
                        ->with('tournament')
                        ->get();

            return response()->json([
                'success' => true,
                'data' => $arenas
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch arenas',
                'error' => $e->getMessage()
            ], 500);
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
