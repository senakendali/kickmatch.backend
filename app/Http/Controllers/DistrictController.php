<?php

namespace App\Http\Controllers;

use App\Models\District;
use Illuminate\Http\Request;

class DistrictController extends Controller
{
    public function index(Request $request)
    {
        // Check for `province_id` query parameter
        $provinceId = $request->query('province_id');

        // Query districts, optionally filtering by province_id
        $districts = District::with('province')
            ->when($provinceId, function ($query, $provinceId) {
                return $query->where('province_id', $provinceId);
            })
            ->get();

        return response()->json($districts);
    }

    public function store(Request $request)
    {
        // Validate input
        $request->validate([
            'province_id' => 'required|exists:provinces,id',
            'name' => 'required|string|max:255',
        ]);

        // Create a new district
        $district = District::create($request->all());
        return response()->json($district, 201);
    }

    public function show($id)
    {
        // Get a single district
        $district = District::with('province')->find($id);

        if (!$district) {
            return response()->json(['error' => 'District not found'], 404);
        }

        return response()->json($district);
    }

    public function update(Request $request, $id)
    {
        // Validate input
        $request->validate([
            'province_id' => 'exists:provinces,id',
            'name' => 'string|max:255',
        ]);

        // Find and update the district
        $district = District::find($id);

        if (!$district) {
            return response()->json(['error' => 'District not found'], 404);
        }

        $district->update($request->all());
        return response()->json($district);
    }

    public function destroy($id)
    {
        // Delete the district
        $district = District::find($id);

        if (!$district) {
            return response()->json(['error' => 'District not found'], 404);
        }

        $district->delete();
        return response()->json(['message' => 'District deleted successfully']);
    }
}

