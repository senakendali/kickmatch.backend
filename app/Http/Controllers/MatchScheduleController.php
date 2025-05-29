<?php

namespace App\Http\Controllers;

use App\Models\MatchSchedule;
use App\Models\MatchScheduleDetail;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MatchScheduleController extends Controller
{
   
    public function index(Request $request)
    {
        try {
            $schedules = MatchSchedule::with([
                'arena',
                'tournament',
                'details.tournamentMatch.pool.matchCategory',
                'details.seniMatch.matchCategory',
            ])
            ->paginate($request->get('per_page', 10));

            return response()->json($schedules, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to fetch match schedules', 'message' => $e->getMessage()], 500);
        }
    }


    public function getSchedules_hampir($slug)
{
    $tournament = Tournament::where('slug', $slug)->firstOrFail();

    $query = MatchScheduleDetail::with([
        'schedule.arena',
        'schedule.tournament',
        'tournamentMatch.participantOne.contingent',
        'tournamentMatch.participantTwo.contingent',
        'tournamentMatch.pool.categoryClass',
        'tournamentMatch.pool.ageCategory',
        'tournamentMatch.pool',
    ])
    ->whereHas('schedule', fn($q) => $q->where('tournament_id', $tournament->id))
    ->whereHas('tournamentMatch');

    if (request()->filled('arena_name')) {
        $query->whereHas('schedule.arena', function ($q) {
            $q->where('name', request()->arena_name);
        });
    }

    if (request()->filled('scheduled_date')) {
        $query->whereHas('schedule', function ($q) {
            $q->where('scheduled_date', request()->scheduled_date);
        });
    }

    if (request()->filled('pool_name')) {
        $query->whereHas('tournamentMatch.pool', function ($q) {
            $q->where('name', request()->pool_name);
        });
    }

    $details = $query->get();

    $tournamentName = $tournament->name ?? 'Tanpa Turnamen';
    $grouped = [];

    foreach ($details as $detail) {
        $arenaName = $detail->schedule->arena->name ?? 'Tanpa Arena';
        $date = $detail->schedule->scheduled_date;
        $pool = $detail->tournamentMatch->pool;
        $poolName = $pool->name ?? 'Tanpa Pool';
        $round = $detail->tournamentMatch->round ?? 0;

        // Ambil total match dari pool tersebut
        $totalMatchInPool = \App\Models\TournamentMatch::where('pool_id', $pool->id)->count();
        $totalRounds = (int) ceil(log($totalMatchInPool + 1, 2));

        // Override jika usia dini (langsung final)
        $ageCategoryId = optional($pool->ageCategory)->id;
        if ($ageCategoryId == 1) {
            $roundLabel = 'Final';
        } else {
            $roundLabel = $this->getRoundLabel($round, $totalRounds);
        }

        // Info kelas dan usia
        $categoryClass = optional($pool->categoryClass);
        $ageCategory = optional($pool->ageCategory);
        $ageCategoryName = $ageCategory->name ?? 'Tanpa Usia';
        $className = $ageCategoryName . ' ' . ($categoryClass->name ?? 'Tanpa Kelas');
        $minWeight = $categoryClass->weight_min ?? null;
        $maxWeight = $categoryClass->weight_max ?? null;

        $matchData = [
            'match_order' => $detail->order,
            'match_time' => $detail->start_time,
            'participant_one' => optional($detail->tournamentMatch->participantOne)->name,
            'participant_two' => optional($detail->tournamentMatch->participantTwo)->name,
            'contingent_one' => optional(optional($detail->tournamentMatch->participantOne)->contingent)->name,
            'contingent_two' => optional(optional($detail->tournamentMatch->participantTwo)->contingent)->name,
            'class_name' => $className . ' (' . $minWeight . ' KG - ' . $maxWeight . ' KG)',
        ];

        $groupKey = $arenaName . '||' . $date;
        $grouped[$groupKey]['arena_name'] = $arenaName;
        $grouped[$groupKey]['scheduled_date'] = $date;
        $grouped[$groupKey]['tournament_name'] = $tournamentName;
        $grouped[$groupKey]['pools'][$poolName]['pool_name'] = $poolName;
        $grouped[$groupKey]['pools'][$poolName]['rounds'][$roundLabel][] = $matchData;
    }

    $result = [];
    foreach ($grouped as $entry) {
        $pools = [];
        foreach ($entry['pools'] as $pool) {
            $rounds = [];
            foreach ($pool['rounds'] as $roundLabel => $matches) {
                $rounds[] = [
                    'round_label' => $roundLabel,
                    'matches' => $matches,
                ];
            }
            $pools[] = [
                'pool_name' => $pool['pool_name'],
                'rounds' => $rounds,
            ];
        }

        $result[] = [
            'arena_name' => $entry['arena_name'],
            'scheduled_date' => $entry['scheduled_date'],
            'tournament_name' => $entry['tournament_name'],
            'pools' => $pools,
        ];
    }

    return response()->json(['data' => $result]);
}

public function getSchedules($slug)
{
    $tournament = Tournament::where('slug', $slug)->firstOrFail();

    $query = MatchScheduleDetail::with([
        'schedule.arena',
        'schedule.tournament',
        'tournamentMatch.participantOne.contingent',
        'tournamentMatch.participantTwo.contingent',
        'tournamentMatch.pool.categoryClass',
        'tournamentMatch.pool.ageCategory',
        'tournamentMatch.pool',
    ])
    ->whereHas('schedule', fn($q) => $q->where('tournament_id', $tournament->id))
    ->whereHas('tournamentMatch');

    if (request()->filled('arena_name')) {
        $query->whereHas('schedule.arena', fn($q) =>
            $q->where('name', request()->arena_name));
    }

    if (request()->filled('scheduled_date')) {
        $query->whereHas('schedule', fn($q) =>
            $q->where('scheduled_date', request()->scheduled_date));
    }

    if (request()->filled('pool_name')) {
        $query->whereHas('tournamentMatch.pool', fn($q) =>
            $q->where('name', request()->pool_name));
    }

    $details = $query->orderBy('order')->get();

    $grouped = [];

    foreach ($details as $detail) {
        $arenaName = $detail->schedule->arena->name ?? 'Tanpa Arena';
        $date = $detail->schedule->scheduled_date;
        $pool = $detail->tournamentMatch->pool;
        $poolName = $pool->name ?? 'Tanpa Pool';
        $round = $detail->tournamentMatch->round ?? 0;

        $totalMatchInPool = \App\Models\TournamentMatch::where('pool_id', $pool->id)->count();
        $totalRounds = (int) ceil(log($totalMatchInPool + 1, 2));

        $categoryClass = optional($pool->categoryClass);
        $ageCategory = optional($pool->ageCategory);
        $ageCategoryName = $ageCategory->name ?? 'Tanpa Usia';
        $className = $ageCategoryName . ' ' . ($categoryClass->name ?? 'Tanpa Kelas');
        $minWeight = $categoryClass->weight_min ?? null;
        $maxWeight = $categoryClass->weight_max ?? null;

        $roundLabel = ($ageCategory->id == 1) ? 'Final' : $this->getRoundLabel($round, $totalRounds);

        $matchData = [
            'match_number' => $detail->tournamentMatch->match_number,
            'match_order' => $detail->order,
            'match_time' => $detail->start_time,
            'participant_one' => optional($detail->tournamentMatch->participantOne)->name,
            'participant_two' => optional($detail->tournamentMatch->participantTwo)->name,
            'contingent_one' => optional(optional($detail->tournamentMatch->participantOne)->contingent)->name,
            'contingent_two' => optional(optional($detail->tournamentMatch->participantTwo)->contingent)->name,
            'class_name' => $className . ' (' . $minWeight . ' KG - ' . $maxWeight . ' KG)',
            'round_label' => $roundLabel,
            'round' => $round,
        ];

        $groupKey = $arenaName . '||' . $date;
        $poolKey = $poolName . '||' . $ageCategoryName;

        $grouped[$groupKey]['arena_name'] = $arenaName;
        $grouped[$groupKey]['scheduled_date'] = $date;
        $grouped[$groupKey]['tournament_name'] = $tournament->name;
        $grouped[$groupKey]['pools'][$poolKey]['pool_name'] = $poolName;
        $grouped[$groupKey]['pools'][$poolKey]['matches'][] = $matchData;
    }

    $result = [];
    $roundPriority = [
        'Preliminary' => 1,
        'Quarterfinal' => 2,
        'Semifinal' => 3,
        'Final' => 4,
    ];

    foreach ($grouped as $entry) {
        $pools = [];

        foreach ($entry['pools'] as $pool) {
            $roundMap = [];
            $sortedMatches = collect($pool['matches'])->sortBy('match_order')->values();

            foreach ($sortedMatches as $match) {
                $roundLabel = $match['round_label'];
                unset($match['round'], $match['round_label']);
                $roundMap[$roundLabel][] = $match;
            }

            $rounds = collect($roundMap)
                ->sortBy(fn($_, $label) => $roundPriority[$label] ?? 99)
                ->map(fn($matches, $label) => [
                    'round_label' => $label,
                    'matches' => $matches,
                ])
                ->values();

            $pools[] = [
                'pool_name' => $pool['pool_name'],
                'rounds' => $rounds,
            ];
        }

        $result[] = [
            'arena_name' => $entry['arena_name'],
            'scheduled_date' => $entry['scheduled_date'],
            'tournament_name' => $entry['tournament_name'],
            'pools' => $pools,
        ];
    }

    return response()->json(['data' => $result]);
}








private function getRoundLabel($round, $totalRounds)
{
    $labels = [
        1 => 'Final',
        2 => 'Semifinal',
        3 => '1/4 Final',
        4 => '1/8 Final',
        5 => '1/16 Final',
        6 => '1/32 Final',
    ];

    $labelIndex = $totalRounds - $round + 1;
    return $labels[$labelIndex] ?? "Babak {$round}";
}









    

    private function getRoundLabels_($totalRounds)
    {
        $labels = [];

        for ($i = 1; $i <= $totalRounds; $i++) {
            if ($totalRounds === 1) {
                $labels[$i] = "Final";
            } elseif ($totalRounds === 2) {
                $labels[$i] = $i === 1 ? "Semifinal" : "Final";
            } elseif ($totalRounds === 3) {
                $labels[$i] = $i === 1 ? "Perempat Final" : ($i === 2 ? "Semifinal" : "Final");
            } else {
                if ($i === 1) {
                    $labels[$i] = "Penyisihan";
                } elseif ($i === $totalRounds - 2) {
                    $labels[$i] = "Perempat Final";
                } elseif ($i === $totalRounds - 1) {
                    $labels[$i] = "Semifinal";
                } elseif ($i === $totalRounds) {
                    $labels[$i] = "Final";
                } else {
                    $labels[$i] = "Babak {$i}";
                }
            }
        }

        return $labels;
    }



    public function show($id)
    {
        try {
            $schedule = MatchSchedule::with([
                'arena',
                'tournament',
                'details' => function ($q) {
                    $q->orderBy('order');
                },
            ])->findOrFail($id);

            // Ambil semua tournament_match_id dari detail
            $matchIds = $schedule->details->pluck('tournament_match_id')->filter();

            // Ambil data match (tanpa relasi)
            $matches = \App\Models\TournamentMatch::whereIn('id', $matchIds)
                ->with(['participantOne.contingent', 'participantTwo.contingent'])
                ->get()
                ->keyBy('id');

            // Masukkan match ke dalam masing-masing detail
            $schedule->details->transform(function ($detail) use ($matches) {
                $detail->tournament_match = $matches->get($detail->tournament_match_id); // bisa null kalau hilang
                return $detail;
            });

            return response()->json(['data' => $schedule], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Match schedule not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred', 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'tournament_arena_id' => 'required|exists:tournament_arena,id',
            'match_category_id' => 'nullable|exists:match_categories,id',
            'scheduled_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'nullable',
            'note' => 'nullable|string',
            'matches' => 'required|array|min:1',
            'matches.*.note' => 'nullable|string',
            'matches.*.tournament_match_id' => 'nullable|exists:tournament_matches,id',
            'matches.*.seni_match_id' => 'nullable|exists:seni_matches,id',
            'matches.*.start_time' => 'nullable',
        ]);

        // ✅ Deteksi duplikat hanya untuk tanding
        $tandingIds = collect($request->matches)
            ->pluck('tournament_match_id')
            ->filter()
            ->toArray();

        $exists = false;

        if (!empty($tandingIds)) {
            $exists = \App\Models\MatchScheduleDetail::whereIn('tournament_match_id', $tandingIds)
                ->whereHas('schedule', function ($q) use ($request) {
                    $q->where('tournament_id', $request->tournament_id)
                    ->where('scheduled_date', $request->scheduled_date);
                })->exists();
        }

        if ($exists) {
            return response()->json(['error' => 'Some matches are already scheduled on this date'], 422);
        }

        DB::beginTransaction();

        try {
            $schedule = \App\Models\MatchSchedule::create([
                'tournament_id' => $request->tournament_id,
                'tournament_arena_id' => $request->tournament_arena_id,
                'scheduled_date' => $request->scheduled_date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'note' => $request->note,
            ]);

            // ✅ Ambil urutan terakhir berdasarkan tipe pertandingan
            $lastOrderTanding = \App\Models\MatchScheduleDetail::whereNotNull('tournament_match_id')
                ->whereHas('schedule', function ($q) use ($request) {
                    $q->where('tournament_id', $request->tournament_id)
                    ->where('scheduled_date', $request->scheduled_date);
                })->max('order') ?? 0;

            $lastOrderSeni = \App\Models\MatchScheduleDetail::whereNotNull('seni_match_id')
                ->whereHas('schedule', function ($q) use ($request) {
                    $q->where('tournament_id', $request->tournament_id)
                    ->where('scheduled_date', $request->scheduled_date);
                })->max('order') ?? 0;

            foreach ($request->matches as $match) {
                $data = [
                    'start_time' => $match['start_time'] ?? null,
                    'note' => $match['note'] ?? null,
                ];

                // 🥋 TANDING
                if (!empty($match['tournament_match_id'])) {
                    $data['tournament_match_id'] = $match['tournament_match_id'];
                    $data['match_category_id'] = $request->match_category_id;
                    $data['order'] = ++$lastOrderTanding;
                }

                // 🎭 SENI
                if (!empty($match['seni_match_id'])) {
                    $seni = \App\Models\SeniMatch::find($match['seni_match_id']);
                    if ($seni) {
                        $data['seni_match_id'] = $seni->id;
                        $data['match_category_id'] = $seni->match_category_id;
                        $data['order'] = ++$lastOrderSeni;
                    }
                }

                $schedule->details()->create($data);
            }

            DB::commit();

            return response()->json([
                'message' => 'Match schedule created successfully',
                'data' => $schedule->load('details')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to create match schedule',
                'message' => $e->getMessage()
            ], 500);
        }
    }




    public function store___(Request $request)
    {
        $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'tournament_arena_id' => 'required|exists:tournament_arena,id',
            'match_category_id' => 'nullable|exists:match_categories,id', // bisa nullable kalau seni
            'scheduled_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'nullable',
            'note' => 'nullable|string',
            'matches' => 'required|array|min:1',
            'matches.*.note' => 'nullable|string',
            'matches.*.tournament_match_id' => 'nullable|exists:tournament_matches,id',
            'matches.*.seni_match_id' => 'nullable|exists:seni_matches,id',
            'matches.*.order' => 'nullable|integer',
            'matches.*.start_time' => 'nullable',
        ]);

        // Cek duplikat (khusus tanding)
        $exists = \App\Models\MatchScheduleDetail::whereIn('tournament_match_id', collect($request->matches)->pluck('tournament_match_id')->filter())
            ->whereHas('schedule', function ($q) use ($request) {
                $q->where('tournament_arena_id', $request->tournament_arena_id)
                ->where('scheduled_date', $request->scheduled_date);
            })->exists();

        if ($exists) {
            return response()->json(['error' => 'Some matches are already scheduled on this arena and date'], 422);
        }

        DB::beginTransaction();

        try {
            $schedule = \App\Models\MatchSchedule::create([
                'tournament_id' => $request->tournament_id,
                'tournament_arena_id' => $request->tournament_arena_id,
                'scheduled_date' => $request->scheduled_date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'note' => $request->note,
            ]);

            $orderCounter = 1;
            foreach ($request->matches as $match) {
                $data = [
                    'start_time' => $match['start_time'] ?? null,
                    'note' => $match['note'] ?? null,
                ];

                // 🥋 TANDING
                if (!empty($match['tournament_match_id'])) {
                    $data['tournament_match_id'] = $match['tournament_match_id'];
                    $data['match_category_id'] = $request->match_category_id;
                    $data['order'] = $match['order'] ?? $orderCounter++;
                }

                // 🎭 SENI
                if (!empty($match['seni_match_id'])) {
                    $seni = \App\Models\SeniMatch::find($match['seni_match_id']);

                    if ($seni) {
                        $data['seni_match_id'] = $seni->id;
                        $data['match_category_id'] = $seni->match_category_id;
                        $data['order'] = $match['order'] ?? $seni->match_order ?? $orderCounter++;
                    }
                }

                $schedule->details()->create($data);
            }

            DB::commit();

            return response()->json([
                'message' => 'Match schedule created successfully',
                'data' => $schedule->load('details')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to create match schedule',
                'message' => $e->getMessage()
            ], 500);
        }
    }



    

    public function update(Request $request, $id)
    {
        $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'tournament_arena_id' => 'required|exists:tournament_arena,id',
            'scheduled_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'nullable',
            'note' => 'nullable|string',
            'matches' => 'required|array|min:1',
            'matches.*.note' => 'nullable|string',

            // Tanding
            'matches.*.tournament_match_id' => 'nullable|exists:tournament_matches,id',

            // Seni
            'matches.*.seni_match_id' => 'nullable|exists:seni_matches,id',

            'matches.*.order' => 'nullable|integer',
            'matches.*.start_time' => 'nullable',
        ]);

        $schedule = \App\Models\MatchSchedule::findOrFail($id);

        DB::beginTransaction();

        try {
            $schedule->update([
                'tournament_id' => $request->tournament_id,
                'tournament_arena_id' => $request->tournament_arena_id,
                'scheduled_date' => $request->scheduled_date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'note' => $request->note,
            ]);

            // Hapus detail lama
            $schedule->details()->delete();

            $orderCounter = 1;
            foreach ($request->matches as $match) {
                $data = [
                    'note' => $match['note'] ?? null,
                    'order' => $match['order'] ?? $orderCounter++,
                    'start_time' => $match['start_time'] ?? null,
                ];

                if (!empty($match['tournament_match_id'])) {
                    $data['tournament_match_id'] = $match['tournament_match_id'];
                    $data['match_category_id'] = $request->match_category_id;
                }

                if (!empty($match['seni_match_id'])) {
                    $data['seni_match_id'] = $match['seni_match_id'];
                }

                $schedule->details()->create($data);
            }

            DB::commit();

            return response()->json([
                'message' => 'Match schedule updated successfully',
                'data' => $schedule->load('details')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to update match schedule',
                'message' => $e->getMessage()
            ], 500);
        }
    }




    public function destroy($id)
    {
        try {
            $schedule = MatchSchedule::findOrFail($id);
            $schedule->details()->delete();
            $schedule->delete();

            return response()->json(['message' => 'Match schedule deleted successfully'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Match schedule not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete match schedule', 'message' => $e->getMessage()], 500);
        }
    }

}
