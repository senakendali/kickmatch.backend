<?php

namespace App\Http\Controllers;

use App\Models\TournamentCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TournamentMatchCategoryController extends Controller
{
    public function index(Request $request)
    {
        try {
            $data = TournamentCategory::with(['tournament', 'matchCategory'])
                ->paginate($request->get('per_page', 10));
            return response()->json($data, 200);
        } catch (\Exception $e) {
            Log::error('Failed to fetch tournament match categories: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch data'], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tournament_id'     => 'required|exists:tournaments,id',
            'match_category_id' => 'required|exists:match_categories,id',
            'registration_fee'  => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $data = TournamentCategory::create([
                'tournament_id'     => $request->tournament_id,
                'match_category_id' => $request->match_category_id,
                'registration_fee'  => $request->registration_fee,
            ]);

            return response()->json(['message' => 'Data saved successfully', 'data' => $data], 201);
        } catch (\Exception $e) {
            Log::error('Failed to store tournament match category: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to store data'], 500);
        }
    }

    public function show($id)
    {
        try {
            $data = TournamentCategory::with(['tournament', 'matchCategory'])->findOrFail($id);
            return response()->json(['data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Not found', 'message' => $e->getMessage()], 404);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'tournament_id'     => 'sometimes|exists:tournaments,id',
            'match_category_id' => 'sometimes|exists:match_categories,id',
            'registration_fee'  => 'sometimes|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        try {
            $category = TournamentCategory::findOrFail($id);
            $category->update($request->only(['tournament_id', 'match_category_id', 'registration_fee']));
            return response()->json(['message' => 'Updated successfully', 'data' => $category], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update data', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $category = TournamentCategory::findOrFail($id);
            $category->delete();
            return response()->json(['message' => 'Deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete data', 'message' => $e->getMessage()], 500);
        }
    }
}
