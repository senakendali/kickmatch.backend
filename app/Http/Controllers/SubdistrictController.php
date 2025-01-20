<?php

namespace App\Http\Controllers;

use App\Models\Subdistrict;
use Illuminate\Http\Request;

class SubdistrictController extends Controller
{
    // Display a listing of subdistricts
    public function index(Request $request)
    {
        $districtId = $request->query('district_id'); // Get the district_id from the query parameter

        if ($districtId) {
            // Filter subdistricts by district_id
            $subdistricts = Subdistrict::where('district_id', $districtId)->get();
        } else {
            // Return all subdistricts if no district_id is provided
            $subdistricts = Subdistrict::all();
        }

        return response()->json($subdistricts, 200);
    }


    // Store a new subdistrict
    public function store(Request $request)
    {
        $validated = $request->validate([
            'district_id' => 'required|exists:districts,id',
            'name' => 'required|string|max:255',
        ]);

        $subdistrict = Subdistrict::create($validated);
        return response()->json($subdistrict, 201);
    }

    // Display a specific subdistrict
    public function show($id)
    {
        $subdistrict = Subdistrict::find($id);

        if (!$subdistrict) {
            return response()->json(['message' => 'Subdistrict not found'], 404);
        }

        return response()->json($subdistrict, 200);
    }

    // Update a specific subdistrict
    public function update(Request $request, $id)
    {
        $subdistrict = Subdistrict::find($id);

        if (!$subdistrict) {
            return response()->json(['message' => 'Subdistrict not found'], 404);
        }

        $validated = $request->validate([
            'district_id' => 'exists:districts,id',
            'name' => 'string|max:255',
        ]);

        $subdistrict->update($validated);
        return response()->json($subdistrict, 200);
    }

    // Delete a specific subdistrict
    public function destroy($id)
    {
        $subdistrict = Subdistrict::find($id);

        if (!$subdistrict) {
            return response()->json(['message' => 'Subdistrict not found'], 404);
        }

        $subdistrict->delete();
        return response()->json(['message' => 'Subdistrict deleted successfully'], 200);
    }
}
