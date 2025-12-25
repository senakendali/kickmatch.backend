<?php

namespace App\Http\Controllers;

use App\Models\TournamentAgeCategory;
use App\Models\EventOrganizer;
use Illuminate\Http\Request;

class TournamentAgeCategoryController extends Controller
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
            $query = TournamentAgeCategory::with(['tournament', 'ageCategory']);

            // casting dulu biar aman
            $roleId = (int) ($user->role_id ?? 0);

            // === HANYA EO YANG DI-FILTER ===
            // owner / admin / role lain dapat semua data
            if ($roleId === 2) { // 2 = EO
                // cari EO profile berdasar user_id
                $organizerId = EventOrganizer::where('user_id', $user->id)->value('id');

                if ($organizerId) {
                    // hanya age category dari tournament yang organizer_id = EO ini
                    $query->whereHas('tournament', function ($q) use ($organizerId) {
                        $q->where('organizer_id', $organizerId);
                    });
                } else {
                    // EO belum punya profile -> balikin kosong tapi tetap berbentuk pagination
                    $query->whereRaw('1 = 0');
                }
            }

            // optional: search by age category name / tournament name
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('ageCategory', function ($sub) use ($search) {
                        $sub->where('name', 'like', '%' . $search . '%');
                    })->orWhereHas('tournament', function ($sub) use ($search) {
                        $sub->where('name', 'like', '%' . $search . '%');
                    });
                });
            }

            $items = $query->paginate($perPage);

            return response()->json($items, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Unable to fetch data',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ambil semua age category untuk 1 tournament (buat dropdown dsb)
     */
    public function getByTournament($tournamentId)
    {
        try {
            $items = TournamentAgeCategory::where('tournament_id', $tournamentId)
                        ->with(['tournament', 'ageCategory'])
                        ->get();

            return response()->json([
                'success' => true,
                'data'    => $items,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch tournament age categories',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $item = TournamentAgeCategory::with(['tournament', 'ageCategory'])->findOrFail($id);
            return response()->json(['data' => $item], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Tournament Age Category not found'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'An error occurred',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'tournament_id'   => 'required|exists:tournaments,id',
            'age_category_id' => 'required|exists:age_categories,id',
            'is_active'       => 'nullable|boolean',
        ]);

        try {
            $user = $request->user();

            $data = [
                'tournament_id'   => $request->tournament_id,
                'age_category_id' => $request->age_category_id,
                'is_active'       => $request->has('is_active')
                    ? (int) $request->boolean('is_active')
                    : 1,
            ];

            if ($user) {
                $data['created_by'] = $user->id;
            }

            $item = TournamentAgeCategory::create($data);

            return response()->json(['data' => $item], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Failed to create Tournament Age Category',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'tournament_id'   => 'sometimes|exists:tournaments,id',
            'age_category_id' => 'sometimes|exists:age_categories,id',
            'is_active'       => 'nullable|boolean',
        ]);

        try {
            $item = TournamentAgeCategory::findOrFail($id);

            $payload = $request->only(['tournament_id', 'age_category_id', 'is_active']);

            if (array_key_exists('is_active', $payload)) {
                $payload['is_active'] = (int) $request->boolean('is_active');
            }

            $item->update($payload);

            return response()->json(['data' => $item], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Tournament Age Category not found'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Failed to update Tournament Age Category',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $item = TournamentAgeCategory::findOrFail($id);
            $item->delete();

            return response()->json([
                'message' => 'Tournament Age Category deleted successfully',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Tournament Age Category not found'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Failed to delete Tournament Age Category',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
