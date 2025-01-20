<?php

namespace App\Http\Controllers;

use App\Models\Ward;
use Illuminate\Http\Request;

class WardController extends Controller
{
    // Display a listing of wards
    public function index(Request $request)
    {
        $subdistrictId = $request->query('subdistrict_id'); // Optional filter by subdistrict_id

        if ($subdistrictId) {
            $wards = Ward::where('subdistrict_id', $subdistrictId)->get();
        } else {
            $wards = Ward::all();
        }

        return response()->json($wards, 200);
    }

    // Store a new ward
    public function store(Request $request)
    {
        $validated = $request->validate([
            'subdistrict_id' => 'required|exists:subdistricts,id',
            'name' => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
        ]);

        $ward = Ward::create($validated);
        return response()->json($ward, 201);
    }

    // Display a specific ward
    public function show($id)
    {
        $ward = Ward::find($id);

        if (!$ward) {
            return response()->json(['message' => 'Ward not found'], 404);
        }

        return response()->json($ward, 200);
    }

    // Update a specific ward
    public function update(Request $request, $id)
    {
        $ward = Ward::find($id);

        if (!$ward) {
            return response()->json(['message' => 'Ward not found'], 404);
        }

        $validated = $request->validate([
            'subdistrict_id' => 'exists:subdistricts,id',
            'name' => 'string|max:255',
            'postal_code' => 'string|max:20',
        ]);

        $ward->update($validated);
        return response()->json($ward, 200);
    }

    // Delete a specific ward
    public function destroy($id)
    {
        $ward = Ward::find($id);

        if (!$ward) {
            return response()->json(['message' => 'Ward not found'], 404);
        }

        $ward->delete();
        return response()->json(['message' => 'Ward deleted successfully'], 200);
    }
}
