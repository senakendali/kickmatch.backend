<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\MatchClasification;
use App\Models\MatchClasificationDetail;

class MatchClasificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            // Use pagination with a default of 10 items per page
            $matchClasifications = MatchClasification::with(['tournament', 'matchCategory', 'ageCategory'])
                ->withCount('matchClasificationDetails')->paginate($request->get('per_page', 10));
            return response()->json($matchClasifications, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to fetch data', 'message' => $e->getMessage()], 500);
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'tournament_id' => 'required|exists:tournaments,id',
            'match_category_id' => 'required|exists:match_categories,id',
            'age_category_id' => 'required|exists:age_categories,id',
            'details' => 'required|array',
            'details.*.category_class_id' => 'required|exists:category_classes,id',
        ]);

        DB::beginTransaction();

        try {
            $matchClasification = MatchClasification::create([
                'name' => $request->name,
                'tournament_id' => $request->tournament_id,
                'match_category_id' => $request->match_category_id,
                'age_category_id' => $request->age_category_id,
            ]);

            foreach ($request->details as $detail) {
                MatchClasificationDetail::create([
                    'match_clasification_id' => $matchClasification->id,
                    'match_category_id' => $request->match_category_id,
                    'category_class_id' => $detail['category_class_id'],
                ]);
            }

            DB::commit();
            return response()->json([
                'message' => 'Match classification created successfully',
                'data' => $matchClasification->load('matchClasificationDetails'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create match classification', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $matchClasification = MatchClasification::with('matchClasificationDetails.categoryClass')->findOrFail($id);
        
            // Group category_class data by gender
            $groupedDetails = $matchClasification->matchClasificationDetails->map(function ($detail) {
                return $detail->categoryClass; // Extract category_class data
            })->groupBy('gender'); // Group by gender
        
            // Format the response
            $data = [
                'id' => $matchClasification->id,
                'name' => $matchClasification->name,
                'tournament_id' => $matchClasification->tournament_id,
                'match_category_id' => $matchClasification->match_category_id,
                'age_category_id' => $matchClasification->age_category_id,
                'created_at' => $matchClasification->created_at,
                'updated_at' => $matchClasification->updated_at,
                'details' => [
                    'male' => $groupedDetails->get('male') ?? [],
                    'female' => $groupedDetails->get('female') ?? [],
                ],
            ];
        
            return response()->json($data, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to fetch data', 'message' => $e->getMessage()], 500);
        }   
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'tournament_id' => 'required|exists:tournaments,id',
            'match_category_id' => 'required|exists:match_categories,id',
            'age_category_id' => 'required|exists:age_categories,id',
            'details' => 'required|array',
            'details.*.category_class_id' => 'required|exists:category_classes,id',
        ]);

        DB::beginTransaction();

        try {
            $matchClasification = MatchClasification::findOrFail($id);
            $matchClasification->update($request->only(['name', 'tournament_id', 'match_category_id', 'age_category_id']));

            if ($request->has('details')) {
                MatchClasificationDetail::where('match_clasification_id', $id)->delete();
                foreach ($request->details as $detail) {
                    MatchClasificationDetail::create([
                        'match_clasification_id' => $matchClasification->id,
                        'match_category_id' => $request->match_category_id,
                        'category_class_id' => $detail['category_class_id'],
                    ]);
                }
                
            }

            

            DB::commit();
            return response()->json(['message' => 'Match classification updated successfully', 'data' => $matchClasification->load('matchClasificationDetails')], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update match classification', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $matchClasification = MatchClasification::findOrFail($id);
            $matchClasification->delete();
            DB::commit();

            return response()->json(['message' => 'Match classification deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete match classification', 'message' => $e->getMessage()], 500);
        }
    }
}
