<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CategoryClass;

class CategoryClassController extends Controller
{
    // Get all category classes
    public function index(Request $request)
    {
        try {
            // Use pagination with a default of 10 items per page
            $categoryClasses = CategoryClass::with('ageCategory')->paginate($request->get('per_page', 10));
            return response()->json($categoryClasses, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to fetch data', 'message' => $e->getMessage()], 500);
        }
    }

    public function fetchByAgeCategory($ageCategoryId)
    {
        try {
            // Fetch CategoryClasses based on the age category
            $categoryClasses = CategoryClass::where('age_category_id', $ageCategoryId)->get();

            // Group by gender
            $groupedByGender = $categoryClasses->groupBy('gender'); // assuming 'gender' is a field in your table
            
            // Optionally, you can make sure to structure the response so male and female are guaranteed keys
            $result = [
                'male' => $groupedByGender->get('male', collect([])), // Return empty collection if 'male' does not exist
                'female' => $groupedByGender->get('female', collect([])), // Return empty collection if 'female' does not exist
            ];

            return response()->json($result, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to fetch data', 'message' => $e->getMessage()], 500);
        }
    }


    // Get a single category class
    public function show($id)
    {
        try {
            $categoryClass = CategoryClass::findOrFail($id);
            return response()->json(['data' => $categoryClass], 200);
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
            'name' => 'required|string|max:255',
            'age_category_id' => 'required|exists:age_categories,id',
            'weight_min' => 'required|numeric|min:0',
            'weight_max' => 'required|numeric|min:0|gt:weight_min',
            'gender' => 'required|in:male,female',
        ]);

        try {
            $categoryClass = CategoryClass::create($request->all());
            return response()->json(['data' => $categoryClass], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create category class', 'message' => $e->getMessage()], 500);
        }
    }

    // Update a category class
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'age_category_id' => 'sometimes|exists:age_categories,id',
            'weight_min' => 'sometimes|numeric|min:0',
            'weight_max' => 'sometimes|numeric|min:0|gt:weight_min',
            'gender' => 'sometimes|in:male,female',
        ]);

        try {
            $categoryClass = CategoryClass::findOrFail($id);
            $categoryClass->update($request->all());
            return response()->json(['data' => $categoryClass], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Category Class not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update category class', 'message' => $e->getMessage()], 500);
        }
    }

    // Delete a category class
    public function destroy($id)
    {
        try {
            $categoryClass = CategoryClass::findOrFail($id);
            $categoryClass->delete();
            return response()->json(['message' => 'Category Class deleted successfully'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Category Class not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete category class', 'message' => $e->getMessage()], 500);
        }
    }
}
