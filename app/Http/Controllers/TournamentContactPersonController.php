<?php

namespace App\Http\Controllers;

use App\Models\TournamentContactPerson;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\EventOrganizer;

class TournamentContactPersonController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', $request->get('perPage', 10));
            $search  = $request->input('search', '');

            // ambil user dari guard (sanctum/passport)
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            // base query
            $query = TournamentContactPerson::with('tournament');

            $roleId = (int) ($user->role_id ?? 0);

            // === HANYA EO YANG DI-FILTER ===
            if ($roleId === 2) { // 2 = EO
                // ambil ID EO (event_organizers.id) berdasarkan user_id
                $organizerId = EventOrganizer::where('user_id', $user->id)->value('id');

                if ($organizerId) {
                    // cuma contact person dari tournament milik EO ini
                    $query->whereHas('tournament', function ($q) use ($organizerId) {
                        $q->where('organizer_id', $organizerId);
                    });
                } else {
                    // EO belum punya profile / belum di-link → kosongin saja
                    $query->whereRaw('1 = 0');
                }
            }
            // admin / owner / role lain: ga di-filter apa pun

            // optional: search
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhere('description', 'like', '%' . $search . '%')
                      ->orWhere('phone', 'like', '%' . $search . '%')
                      ->orWhere('email', 'like', '%' . $search . '%');
                });
            }

            $contacts = $query->paginate($perPage);

            return response()->json($contacts, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Unable to fetch data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $contact = TournamentContactPerson::findOrFail($id);
            return response()->json(['data' => $contact], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Contact Person not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred', 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'description'   => 'required|string',
            'name'          => 'required|string|max:255',
            'phone'         => 'required|string|max:20',
            'email'         => 'nullable|email|max:255',
        ]);

        try {
            $contact = TournamentContactPerson::create($request->all());
            return response()->json(['data' => $contact], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create contact person', 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'tournament_id' => 'sometimes|exists:tournaments,id',
            'description'   => 'sometimes|string',
            'name'          => 'sometimes|string|max:255',
            'phone'         => 'sometimes|string|max:20',
            'email'         => 'nullable|email|max:255',
        ]);

        try {
            $contact = TournamentContactPerson::findOrFail($id);
            $contact->update($request->all());
            return response()->json(['data' => $contact], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Contact Person not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update contact person', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $contact = TournamentContactPerson::findOrFail($id);
            $contact->delete();
            return response()->json(['message' => 'Contact person deleted successfully'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Contact Person not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete contact person', 'message' => $e->getMessage()], 500);
        }
    }
}
