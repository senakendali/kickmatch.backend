<?php

namespace App\Http\Controllers;

use App\Models\TournamentContactPerson;
use Illuminate\Http\Request;

class TournamentContactPersonController extends Controller
{
    public function index(Request $request)
    {
        try {
            $contacts = TournamentContactPerson::with('tournament')->paginate($request->get('per_page', 10));
            return response()->json($contacts, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to fetch data', 'message' => $e->getMessage()], 500);
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
