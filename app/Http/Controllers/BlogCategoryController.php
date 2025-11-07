<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BlogCategoryController extends Controller
{
    public function index(Request $request)
    {
        try {
            $data = BlogCategory::paginate($request->get('per_page', 10));
            return response()->json($data, 200);
        } catch (\Exception $e) {
            Log::error('Failed to fetch blog categories: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch data'], 500);
        }
    }

    public function store(Request $request)
    {
        // $input = $request->all();
        //dd($input); exit;
        
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string',
            //'description' => 'required|string',
            'is_active' => 'required|boolean',
            //'ordering' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {

            // Generate slug dari name
            $slug = Str::slug($request->name);

            // Pastikan slug unik
            $originalSlug = $slug;
            $counter = 1;
            while (\App\Models\BlogCategory::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter++;
            }

            $data = BlogCategory::create([
                'name'     => $request->name,
                'slug'     => $slug,
                'description' => $request->description,
                //'parent_id' => $request->parent_id,
                'is_active' => $request->is_active,
                'ordering' => $request->ordering,
            ]);

            return response()->json(['message' => 'Data saved successfully', 'data' => $data], 201);
        } catch (\Exception $e) {
            Log::error('Failed to store blog category: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to store data'], 500);
        }
    }

    public function show($id)
    {
        try {
            $data = BlogCategory::findOrFail($id);
            return response()->json(['data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Not found', 'message' => $e->getMessage()], 404);
        }
    }

    public function update(Request $request, $id){
        //dd($request->all()); exit;
        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'sometimes|string',
            'is_active' => 'required|boolean',
            'ordering' => 'sometimes|integer',
        ]);

        // Handle slug jika name berubah
        if ($request->has('name')) {
            $blog = BlogCategory::findOrFail($id);
            $slug = Str::slug($request->name);
            $originalSlug = $slug;
            $counter = 1;

            while (\App\Models\BlogCategory::where('slug', $slug)->where('id', '!=', $blog->id)->exists()) {
                $slug = $originalSlug . '-' . $counter++;
            }

            $validated['slug'] = $slug;
        }

        try {
            $category = BlogCategory::findOrFail($id);
            $category->update($validated);
            return response()->json(['message' => 'Updated successfully', 'data' => $category], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update data', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $category = BlogCategory::findOrFail($id);
            $category->delete();
            return response()->json(['message' => 'Deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete data', 'message' => $e->getMessage()], 500);
        }
    }
}
