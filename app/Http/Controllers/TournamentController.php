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
use App\Models\Contingent;
use App\Models\TeamMember;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class TournamentController extends Controller
{
    public function __construct()
    {
        setlocale(LC_TIME, 'id_ID');
    }
    public function index(Request $request)
    {
        // Check apakah fetch_all bernilai true atau false
        $fetchAll = $request->query('fetch_all', false);

        if ($fetchAll) {
            // Ambil semua data tanpa pagination dan order by id desc
            $members = Tournament::orderBy('id', 'desc')->get();
        } else {
            // Ambil data dengan pagination 10 item per halaman dan order by id desc
            $members = Tournament::orderBy('id', 'desc')->paginate(10);
        }

        return response()->json($members, 200);
    }


    public function getTournamentGallery()
    {
        try {
            $gallery = Tournament::orderBy('id', 'desc')->get()->map(function ($tournament) {
                return [
                    'id'       => $tournament->id,
                    'name'     => $tournament->name,
                    'slug'     => $tournament->slug,
                    'document' => $tournament->document,
                    'image'    => asset('banner/' . $tournament->image),
                    'status'   => $tournament->status,
                ];
            });

            return response()->json(['data' => $gallery], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'An error occurred',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    
    function getActiveTournament(){
        try {
            $activeTournament = Tournament::where('status', 'active')->get();
            return response()->json($activeTournament, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred', 'message' => $e->getMessage()], 500);
        }
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
                        'registration_fee' => $category->registration_fee,
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
                'contact_persons' => $tournament->tournamentContactPersons->map(function ($contactPerson) {
                    return [
                        'id' => $contactPerson->id,
                        'name' => $contactPerson->name,
                        'description' => $contactPerson->description,
                        'phone' => $contactPerson->phone,
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
                        'registration_fee' => $category->registration_fee,
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
                'contact_persons' => $tournament->tournamentContactPersons->map(function ($contactPerson) {
                    return [
                        'id' => $contactPerson->id,
                        'name' => $contactPerson->name,
                        'description' => $contactPerson->description,
                        'phone' => $contactPerson->phone,
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
                        'registration_fee' => $category->registration_fee,
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
                'contact_persons' => $tournament->tournamentContactPersons->map(function ($contactPerson) {
                    return [
                        'id' => $contactPerson->id,
                        'name' => $contactPerson->name,
                        'description' => $contactPerson->description,
                        'phone' => $contactPerson->phone,
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
    
        // If the month and year are the same for both dates
        if ($startDate->month === $endDate->month && $startDate->year === $endDate->year) {
            $formattedDateRange = $startDate->day . ' - ' . $endDate->day . ' ' . $months[$startDate->month] . ' ' . $startDate->year;
        } else {
            $formattedDateRange = $startDate->day . ' ' . $months[$startDate->month] . ' ' . $startDate->year . ' - ' . $endDate->day . ' ' . $months[$endDate->month] . ' ' . $endDate->year;
        }
    
        return $formattedDateRange;
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

    public function getTournamentStats($tournament_id)
    {
        // Get all tournament contingents
        $tournamentContingents = TournamentContingent::where('tournament_id', $tournament_id)
            ->with('contingent.teamMembers')
            ->get();

        // Count total contingents
        $totalContingents = $tournamentContingents->count();

        // Initialize counts
        $totalMembers = 0;
        $tandingCount = 0;
        $seniCount = 0;

        // Loop through each contingent and sum up members
        foreach ($tournamentContingents as $tc) {
            $teamMembers = $tc->contingent->teamMembers ?? collect();
            
            $totalMembers += $teamMembers->count();
            $tandingCount += $teamMembers->where('championship_category_id', 1)->count();
            $seniCount += $teamMembers->where('championship_category_id', 2)->count();
        }

        return response()->json([
            'total_contingents' => $totalContingents,
            'total_members' => $totalMembers,
            'tanding' => $tandingCount,
            'seni' => $seniCount
        ], 200);
    }

    public function getContingentsWithStats($tournament_id)
    {
        // Get all contingents in the tournament
        $tournamentContingents = TournamentContingent::where('tournament_id', $tournament_id)
            ->with('contingent.teamMembers')
            ->get();

        // Format response data
        $contingentsData = $tournamentContingents->map(function ($tc) {
            $teamMembers = $tc->contingent->teamMembers ?? collect();

            return [
                'contingent_name' => $tc->contingent->name ?? 'Unknown', 
                'total_members' => $teamMembers->count(),
                'total_tanding' => $teamMembers->where('championship_category_id', 1)->count(),
                'total_seni' => $teamMembers->where('championship_category_id', 2)->count(),
            ];
        });

        return response()->json($contingentsData, 200);
    }

    public function summaryByProvince($tournamentId)
    {
        // Ambil semua kontingen yang ikut dalam turnamen tertentu
        $contingents = Contingent::whereHas('tournamentContingents', function ($query) use ($tournamentId) {
            $query->where('tournament_id', $tournamentId);
        })
        ->with('province')
        ->get();

        // Kelompokkan berdasarkan provinsi
        $summary = $contingents->groupBy('province.name')->map(function ($items) {
            return [
                'total_contingents' => $items->count(),
                'contingents' => $items->map(function ($contingent) {
                    return [
                        'id' => $contingent->id,
                        'name' => $contingent->name,
                    ];
                }),
            ];
        });

        return response()->json($summary);
    }

    public function getParticipantsByProvince($tournamentId)
    {
        $summary = TeamMember::selectRaw('provinces.name as province, COUNT(team_members.id) as total_participants')
            ->join('provinces', 'team_members.province_id', '=', 'provinces.id')
            ->whereHas('contingent.tournamentContingents', function ($query) use ($tournamentId) {
                $query->where('tournament_id', $tournamentId);
            })
            ->groupBy('provinces.name')
            ->orderByDesc('total_participants')
            ->get();

        return response()->json($summary);
    }

    public function getParticipantsByAgeCategory($tournamentId)
    {
        $summary = TeamMember::selectRaw('age_categories.name as age_category, COUNT(team_members.id) as total_participants')
            ->join('age_categories', 'team_members.age_category_id', '=', 'age_categories.id')
            ->whereHas('contingent.tournamentContingents', function ($query) use ($tournamentId) {
                $query->where('tournament_id', $tournamentId);
            })
            ->groupBy('age_categories.name')
            ->orderByDesc('total_participants')
            ->get();

        return response()->json($summary);
    }

    public function getParticipantsByCategoryClass($tournamentId)
    {
        // Ambil contingent_id yang terhubung dengan tournament_id
        $contingentIds = TournamentContingent::where('tournament_id', $tournamentId)
            ->pluck('contingent_id')
            ->toArray();

        if (empty($contingentIds)) {
            return response()->json([]);
        }

        // Ambil jumlah peserta berdasarkan category_class_id dan join dengan category_classes
        $participants = TeamMember::whereIn('team_members.contingent_id', $contingentIds)
            ->join('category_classes', 'team_members.category_class_id', '=', 'category_classes.id')
            ->join('age_categories', 'category_classes.age_category_id', '=', 'age_categories.id')
            ->select(
                'team_members.category_class_id',
                'age_categories.name as age_category_name',
                'category_classes.name as class_name',
                \DB::raw('COUNT(team_members.id) as total_participants')
            )
            ->groupBy('team_members.category_class_id', 'category_classes.name', 'age_categories.name')
            ->get();

        return response()->json($participants);
    }


    public function getParticipantsByDistrict($tournamentId)
    {
        $participants = TeamMember::whereHas('contingent.tournamentContingents', function ($query) use ($tournamentId) {
                $query->where('tournament_id', $tournamentId);
            })
            ->select('district_id', \DB::raw('COUNT(*) as total_participants'))
            ->groupBy('district_id')
            ->with('district') // Menghubungkan dengan model District
            ->get()
            ->map(function ($participant) {
                return [
                    'district_id' => $participant->district_id,
                    'district_name' => optional($participant->district)->name, // Mengambil nama district jika tersedia
                    'total_participants' => $participant->total_participants,
                ];
            });

        return response()->json($participants);
    }



}
