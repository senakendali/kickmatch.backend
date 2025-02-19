<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TournamentMatch;
use App\Models\Tournament;
use App\Models\TeamMember;

class DrawingController extends Controller
{
    public function index()
    {
        // Ambil semua pertandingan
        $matches = TournamentMatch::with(['participant1', 'participant2', 'participant1.contingent', 'participant2.contingent'])
            ->get();

        // Format data pertandingan
        $matchesData = $matches->map(function ($match) {
            return [
                'match_id' => $match->id,
                'round' => $match->round,
                'team_member_1_name' => $match->participant1?->name ?? 'TBD',
                'team_member_2_name' => $match->participant2?->name ?? 'TBD',
                'team_member_1_contingent' => $match->participant1?->contingent?->name ?? 'TBD',
                'team_member_2_contingent' => $match->participant2?->contingent?->name ?? 'TBD',
                'winner' => $match->winner_id ? $match->winner->name : 'TBD',
            ];
        });
        
        return response()->json([
            'data' => $matchesData
        ]);
    }

    
    public function store(Request $request)
{
    $validated = $request->validate([
        'tournament_id' => 'required|exists:tournaments,id',
        'match_category_id' => 'required|exists:match_categories,id',
        'age_category_id' => 'required|exists:age_categories,id',
    ]);

    $classes = TeamMember::select('category_class_id as class_id')
        ->distinct()
        ->pluck('class_id');

    foreach ($classes as $classId) {
        $participants = TeamMember::where('category_class_id', $classId)->get()->shuffle();
        $totalParticipants = $participants->count();

        if ($totalParticipants < 2) {
            continue; // Skip jika peserta kurang dari 2
        }

        $nextPowerOfTwo = pow(2, ceil(log($totalParticipants, 2)));
        $preliminaryMatches = $totalParticipants - ($nextPowerOfTwo / 2);
        $round = 1;
        $newParticipants = collect();
        $babakKeduaMatches = collect(); // Simpan pertandingan babak kedua

        // **Babak Pendahuluan**
        $match1 = null;
        $match2 = null;

        for ($i = 0; $i < $preliminaryMatches * 2; $i += 2) {
            $p1 = $participants[$i];
            $p2 = $participants[$i + 1];

            $match = TournamentMatch::create([
                'tournament_id' => $validated['tournament_id'],
                'match_category_id' => $validated['match_category_id'],
                'age_category_id' => $validated['age_category_id'],
                'round' => $round,
                'team_member_1_id' => $p1->id,
                'team_member_2_id' => $p2->id,
                'class_id' => $classId
            ]);

            $newParticipants->push((object) ['match_id' => $match->id, 'id' => null]);

            if (!$match1) {
                $match1 = $match;
            } else {
                $match2 = $match;
            }
        }

        // **Babak Kedua**
        $round++;
        $matchTop = null;
        $matchBottom = null;

        for ($i = $preliminaryMatches * 2; $i < $totalParticipants; $i++) {
            $p1 = $participants[$i];

            $match = TournamentMatch::create([
                'tournament_id' => $validated['tournament_id'],
                'match_category_id' => $validated['match_category_id'],
                'age_category_id' => $validated['age_category_id'],
                'round' => $round,
                'team_member_1_id' => $p1->id,
                'team_member_2_id' => null, // TBD
                'class_id' => $classId
            ]);

            $babakKeduaMatches->push($match);

            if (!$matchTop) {
                $matchTop = $match;
            } else {
                $matchBottom = $match;
            }
        }

        // **Atur next_match_id dari babak pendahuluan ke babak kedua**
        if ($match1 && $matchTop) {
            TournamentMatch::where('id', $match1->id)->update(['next_match_id' => $matchTop->id]);
        }
        if ($match2 && $matchBottom) {
            TournamentMatch::where('id', $match2->id)->update(['next_match_id' => $matchBottom->id]);
        }

        // **Babak Berikutnya (Knockout)**
        $round++;
        $finalMatch = TournamentMatch::create([
            'tournament_id' => $validated['tournament_id'],
            'match_category_id' => $validated['match_category_id'],
            'age_category_id' => $validated['age_category_id'],
            'round' => $round,
            'team_member_1_id' => null, // TBD
            'team_member_2_id' => null, // TBD
            'class_id' => $classId
        ]);

        TournamentMatch::where('id', $matchTop->id)->update(['next_match_id' => $finalMatch->id]);
        TournamentMatch::where('id', $matchBottom->id)->update(['next_match_id' => $finalMatch->id]);
    }

    return response()->json(['message' => 'Tournament brackets generated successfully']);
}




    




    











    
    /**
     * Mencari lawan dengan tim yang berbeda
     */
    private function findOpponent($participants, $p1)
    {
        // Jika p1 sudah mendapatkan BYE (tidak memiliki lawan), lewati
        if ($p1 === null) {
            return null; // BYE tidak membutuhkan lawan
        }
    
        // Cari peserta yang tidak berasal dari tim yang sama dan bukan peserta yang mendapatkan BYE
        foreach ($participants as $key => $p2) {
            if ($p2 === null) {
                continue; // Lewati peserta yang mendapatkan BYE
            }
    
            // Cek jika peserta p2 adalah lawan yang valid
            if (isset($p1->contingent_id) && isset($p2->contingent_id)) {
                if ($p2->contingent_id !== $p1->contingent_id) {
                    return $participants->splice($key, 1)->first();
                }
            }
        }
    
        // Jika tidak ditemukan lawan valid, kembalikan peserta terakhir (BYE)
        return $participants->pop();
    }
    
    public function generateBracket($tournamentId, $matchCategoryId, $ageCategoryId)
    {
        $matches = TournamentMatch::with([
                'participant1',
                'participant2',
                'participant1.contingent',
                'participant2.contingent'
            ])
            ->where('tournament_id', $tournamentId)
            ->where('match_category_id', $matchCategoryId)
            ->where('age_category_id', $ageCategoryId)
            ->orderBy('round')
            ->get();

        $bracket = [];
        $winners = [];

        foreach ($matches as $match) {
            $participant1 = $match->participant1;
            $participant2 = $match->participant2;

            $bracket[$match->round][] = [
                'match_id' => $match->id,
                'round' => $match->round,
                'next_match_id' => $match->next_match_id,
                'player1' => [
                    'id' => $participant1?->id ?? null,
                    'name' => $participant1?->name ?? 'TBD',
                    'contingent' => $participant1?->contingent?->name ?? 'TBD',
                    'winner' => $match->winner_id === $participant1?->id,
                ],
                'player2' => [
                    'id' => $participant2?->id ?? null,
                    'name' => $participant2?->name ?? 'TBD',
                    'contingent' => $participant2?->contingent?->name ?? 'TBD',
                    'winner' => $match->winner_id === $participant2?->id,
                ],
            ];

            // Tentukan pemenang untuk ronde berikutnya
            if ($match->winner_id) {
                $winners[$match->round + 1][] = $match->winner_id;
            } else {
                $winners[$match->round + 1][] = "TBD";
            }
        }

        $bracket = $this->addNextRounds($bracket, $winners);

        return response()->json([
            'bracket' => $this->formatBracketForVue($bracket),
            'winners' => $winners,
            'finalMatch' => $this->buildFinalBracket($winners)
        ]);
    }



    private function formatBracketForVue($bracket)
    {
        $formattedBracket = [];

        foreach ($bracket as $round => $matches) {
            $formattedMatches = [];

            foreach ($matches as $match) {
                $formattedMatches[] = [
                    'id' => $match['match_id'],
                    'next' => $match['next_match_id'],
                    'player1' => [
                        'id' => $match['player1']['id'],
                        'name' => $match['player1']['name'],
                        'winner' => $match['player1']['winner'],
                    ],
                    'player2' => [
                        'id' => $match['player2']['id'],
                        'name' => $match['player2']['name'],
                        'winner' => $match['player2']['winner'],
                    ],
                ];
            }

            $formattedBracket[] = [
                'round' => $round,
                'matches' => $formattedMatches,
            ];
        }

        return $formattedBracket;
    }



    private function addNextRounds($bracket, $winners)
    {
        $maxRounds = count($bracket);
        for ($round = 1; $round <= $maxRounds; $round++) {
            if (!isset($bracket[$round]) && isset($winners[$round])) {
                $nextRoundMatches = [];

                for ($i = 0; $i < count($winners[$round]); $i += 2) {
                    $participant1 = $winners[$round][$i] ?? "TBD";
                    $participant2 = $winners[$round][$i + 1] ?? "TBD";

                    $nextRoundMatches[] = [
                        'match_id' => "TBD",
                        'round' => $round,
                        'next_match_id' => $match->next_match_id,
                        'team_member_1_name' => $participant1 === "TBD" ? "TBD" : $this->getParticipantName($participant1),
                        'team_member_2_name' => $participant2 === "TBD" ? "TBD" : $this->getParticipantName($participant2),
                        'team_member_1_contingent' => "TBD",
                        'team_member_2_contingent' => "TBD",
                        'winner' => "TBD",
                    ];
                }
                $bracket[$round] = $nextRoundMatches;
            }
        }

        return $bracket;
    }

    private function getParticipantName($participantId)
    {
        if ($participantId === "TBD") return "TBD";

        $participant = TournamentParticipant::find($participantId);
        return $participant ? $participant->name : "TBD";
    }

    private function buildFinalBracket($winners)
    {
        $finalParticipants = array_slice($winners, -2);

        return [
            'final_match' => [
                'participants' => $finalParticipants
            ]
        ];
    }


    public function show($id)
    {
        // Implementasi Show jika diperlukan
    }

    public function update(Request $request, $id)
    {
        // Implementasi Update jika diperlukan
    }

    public function destroy($id)
    {
        // Implementasi Destroy jika diperlukan
    }
}
