<?php

namespace App\Http\Controllers;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\TournamentAgeCategory;
use App\Models\AgeCategory;
use App\Models\CategoryClass;
use App\Models\TournamentClass;
use App\Models\TournamentActivity;
use App\Models\MatchCategory;
use App\Models\TournamentContingent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class TournamentController extends Controller
{
    public function __construct()
    {
        setlocale(LC_TIME, 'id_ID');
    }
    public function index()
    {
        $members = Tournament::paginate(10); // Paginate 10 items per page return response()->json($members);
        return response()->json($members, 200); 
    }

    public function getHighlightedTournament()
    {
        try {
            $highlightedTournament = Tournament::where('is_highlight', 1)->first();
            if (!$highlightedTournament) {
                return response()->json(['message' => 'No highlighted tournament found.'], 404);
            } 

            // Retrieve the tournament with its relationships
            $tournament = Tournament::with([
                'tournamentActivities',
                'tournamentCategories.matchCategory',
                'tournamentAgeCategories.ageCategory',
            ])->findOrFail($highlightedTournament->id);

            // Transform the data into a structured format
            $data = [
                'id' => $tournament->id,
                'slug' => $tournament->slug,
                'name' => $tournament->name,
                'description' => $tournament->description,
                'start_date' => $tournament->start_date,
                'end_date' => $tournament->end_date,
                'status' => $tournament->status,
                'image' => $tournament->image,
                'document' => $tournament->document,
                'location' => $tournament->location,
                'technical_meeting_date' => $tournament->technical_meeting_date,
                'event_date' => $this->formatEventDate($tournament->start_date, $tournament->end_date), // Example format
                'activities' => $tournament->tournamentActivities->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'name' => $activity->name,
                        'description' => $activity->description,
                        'start_date' => $activity->start_date,
                        'end_date' => $activity->end_date,
                    ];
                }),
                'categories' => $tournament->tournamentCategories->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => optional($category->matchCategory)->name, // Handle null
                        'description' => $category->description,
                    ];
                }),
                'age_categories' => $tournament->tournamentAgeCategories->map(function ($ageCategory) {
                    return [
                        'id' => $ageCategory->id,
                        'name' => $ageCategory->ageCategory->name,
                        'min_age' => $ageCategory->ageCategory->min_age,
                        'max_age' => $ageCategory->ageCategory->max_age,
                    ];
                }),
            ];

            // Return the structured response
            return response()->json($data, 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // If the tournament is not found, return a 404 error
            return response()->json(['error' => 'Tournament not found.'], 404);
        } catch (\Exception $e) {
            // Catch other exceptions and log them
            return response()->json([
                'error' => 'An error occurred.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getTournamentDetail($slug)
    {
        try {
            $detail = Tournament::where('slug', $slug)->first();
            if (!$detail) {
                return response()->json(['message' => 'No tournament found.'], 404);
            } 

            // Retrieve the tournament with its relationships
            $tournament = Tournament::with([
                'tournamentActivities',
                'tournamentCategories.matchCategory',
                'tournamentAgeCategories.ageCategory',
            ])->findOrFail($detail->id);

            // Transform the data into a structured format
            $data = [
                'id' => $tournament->id,
                'slug' => $tournament->slug,
                'name' => $tournament->name,
                'description' => $tournament->description,
                'start_date' => $tournament->start_date,
                'end_date' => $tournament->end_date,
                'status' => $tournament->status,
                'image' => asset('banner/' . $tournament->image),
                'document' => $tournament->document,
                'location' => $tournament->location,
                'technical_meeting_date' => $tournament->technical_meeting_date,
                'event_date' => $this->formatEventDate($tournament->start_date, $tournament->end_date), // Example format
                'activities' => $tournament->tournamentActivities->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'name' => $activity->name,
                        'description' => $activity->description,
                        'start_date' => $activity->start_date,
                        'end_date' => $activity->end_date,
                    ];
                }),
                'categories' => $tournament->tournamentCategories->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => optional($category->matchCategory)->name, // Handle null
                        'description' => $category->description,
                    ];
                }),
                'age_categories' => $tournament->tournamentAgeCategories->map(function ($ageCategory) {
                    return [
                        'id' => $ageCategory->id,
                        'name' => $ageCategory->ageCategory->name,
                        'min_age' => $ageCategory->ageCategory->min_age,
                        'max_age' => $ageCategory->ageCategory->max_age,
                    ];
                }),
            ];

            // Return the structured response
            return response()->json($data, 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // If the tournament is not found, return a 404 error
            return response()->json(['error' => 'Tournament not found.'], 404);
        } catch (\Exception $e) {
            // Catch other exceptions and log them
            return response()->json([
                'error' => 'An error occurred.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            // Retrieve the tournament with its relationships
            $tournament = Tournament::with([
                'tournamentActivities',
                'tournamentCategories.matchCategory',
                'tournamentAgeCategories.ageCategory',
            ])->findOrFail($id);

            // Transform the data into a structured format
            $data = [
                'id' => $tournament->id,
                'slug' => $tournament->slug,
                'name' => $tournament->name,
                'description' => $tournament->description,
                'start_date' => $tournament->start_date,
                'end_date' => $tournament->end_date,
                'status' => $tournament->status,
                'image' => asset('banner/' . $tournament->image),
                'document' => $tournament->document,
                'location' => $tournament->location,
                'technical_meeting_date' => $tournament->technical_meeting_date,
                'event_date' => $this->formatEventDate($tournament->start_date, $tournament->end_date), // Example format
                'activities' => $tournament->tournamentActivities->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'name' => $activity->name,
                        'description' => $activity->description,
                        'start_date' => $activity->start_date,
                        'end_date' => $activity->end_date,
                    ];
                }),
                'categories' => $tournament->tournamentCategories->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => optional($category->matchCategory)->name, // Handle null
                        'description' => $category->description,
                    ];
                }),
                'age_categories' => $tournament->tournamentAgeCategories->map(function ($ageCategory) {
                    return [
                        'id' => $ageCategory->id,
                        'name' => $ageCategory->ageCategory->name,
                        'min_age' => $ageCategory->ageCategory->min_age,
                        'max_age' => $ageCategory->ageCategory->max_age,
                    ];
                }),
            ];

            // Return the structured response
            return response()->json($data, 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // If the tournament is not found, return a 404 error
            return response()->json(['error' => 'Tournament not found.'], 404);
        } catch (\Exception $e) {
            // Catch other exceptions and log them
            return response()->json([
                'error' => 'An error occurred.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function formatEventDate($startDate, $endDate) {
       

        // Create the start and end dates
        $startDate = Carbon::createFromFormat('Y-m-d', $startDate);
        $endDate = Carbon::createFromFormat('Y-m-d', $endDate);

        // Define the months in Indonesian
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei',
            6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober',
            11 => 'November', 12 => 'Desember'
        ];

        // Format the start and end dates
        $startDay = $startDate->day;
        $endDay = $endDate->day;
        $monthYear = $months[$startDate->month] . ' ' . $startDate->year;

        // Combine the parts into the desired format
        $formattedDateRange = "$startDay-$endDay $monthYear";

        return $formattedDateRange; // Output: 21-23 Februari 2025

    }

    public function contingentRegistration(Request $request)
    {
        // Validate the request
        $rules = [
            'tournament_id' => 'required|exists:tournaments,id',
            'contingents' => 'required|array|min:1',
            'contingents.*' => 'exists:contingents,id', // Each element in the array must exist in `contingents` table
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Insert data
        $tournament_id = $request->tournament_id;
        $contingents = $request->contingents;

        foreach ($contingents as $contingent_id) {
            TournamentContingent::create([
                'tournament_id' => $tournament_id,
                'contingent_id' => $contingent_id,
            ]);
        }

        return response()->json(['message' => 'Contingents registered successfully.'], 200);
    }

    




}
