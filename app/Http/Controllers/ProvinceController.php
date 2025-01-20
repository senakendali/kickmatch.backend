<?php

namespace App\Http\Controllers;

use App\Models\Province;
use Illuminate\Http\Request;

class ProvinceController extends Controller
{
    public function index()
    {
        // Get all provinces
        return response()->json(Province::all());
    }

    public function store(Request $request)
    {
        // Validate and create a new province
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $province = Province::create($validated);

        return response()->json($province, 201);
    }

    public function show(Province $province)
    {
        // Show a single province
        return response()->json($province);
    }

    public function update(Request $request, Province $province)
    {
        // Validate and update a province
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
        ]);

        $province->update($validated);

        return response()->json($province);
    }

    public function destroy(Province $province)
    {
        // Delete a province
        $province->delete();

        return response()->json(null, 204);
    }
}

