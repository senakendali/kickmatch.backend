<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TournamentMatch;
use App\Models\Tournament;
use App\Models\TeamMember;
use Illuminate\Support\Facades\DB;

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
        try {
            DB::beginTransaction(); // Mulai transaksi

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

                // **Menyesuaikan jumlah peserta agar menjadi kekuatan 2**
                $nearestPowerOfTwo = pow(2, ceil(log($totalParticipants, 2))); // Mendapatkan angka terdekat yang merupakan kekuatan 2
                
                /*return response()->json([
                    'totalParticipants' => $totalParticipants,
                    'nearestPowerOfTwo' => $nearestPowerOfTwo
                ]);*/
                if($nearestPowerOfTwo > $totalParticipants) {
                    $extraParticipants =  $nearestPowerOfTwo - $totalParticipants;  
                }else{
                    $extraParticipants = $totalParticipants - $nearestPowerOfTwo;
                }
                
                $preliminaryMatches = max(0, $extraParticipants); // Menyesuaikan jumlah pertandingan pendahuluan
                $preliminaryWinners = collect();
                $matchList = collect();

                /*return response()->json([
                    'preliminaryWinners' => $preliminaryWinners,
                    'matchList' => $matchList
                ]);*/

                // **Babak Pendahuluan - Jika ada lebih dari 2 peserta yang tidak sesuai kekuatan 2**
                for ($i = 0; $i < $preliminaryMatches * 2; $i += 2) {
                    $p1 = $participants[$i];
                    $p2 = $participants[$i + 1];

                    $match = TournamentMatch::create([
                        'tournament_id' => $validated['tournament_id'],
                        'match_category_id' => $validated['match_category_id'],
                        'age_category_id' => $validated['age_category_id'],
                        'round' => 1,
                        'team_member_1_id' => $p1->id,
                        'team_member_2_id' => $p2->id,
                        'class_id' => $classId
                    ]);

                    $preliminaryWinners->push($match->id);
                    $matchList->push($match->id);
                }

                // **Babak Kedua - Pemenang babak pendahuluan langsung lolos ke babak kedua**
                $remainingParticipants = $participants->slice($preliminaryMatches * 2)->values();
                $matchSlots = collect();

                // Masukkan peserta yang langsung lolos ke babak kedua
                foreach ($remainingParticipants as $p) {
                    $matchSlots->push($p->id);
                }

                // **Tambahkan pemenang babak pendahuluan ke babak kedua**
                foreach ($preliminaryWinners as $winnerId) {
                    $matchSlots->push(null);
                }



                // **Atur pertandingan babak kedua berdasarkan match slots**
                $secondRoundMatches = collect();
                $round = 2;
                for ($i = 0; $i < $matchSlots->count(); $i += 2) {
                    // Jika peserta ganjil, otomatis dapat BYE
                    $team1 = $matchSlots[$i] ?? null;
                    $team2 = $matchSlots[$i + 1] ?? null;

                    $match = TournamentMatch::create([
                        'tournament_id' => $validated['tournament_id'],
                        'match_category_id' => $validated['match_category_id'],
                        'age_category_id' => $validated['age_category_id'],
                        'round' => $round,
                        'team_member_1_id' => $team1,
                        'team_member_2_id' => $team2,
                        'class_id' => $classId
                    ]);

                    $secondRoundMatches->push($match->id);
                    $matchList->push($match->id);
                }

                // Update next_match_id untuk babak-babak yang sesuai
                $secondRoundMatchIds = $secondRoundMatches->toArray();
                for ($i = 0; $i < count($secondRoundMatchIds); $i++) {
                    $nextMatchId = $secondRoundMatchIds[$i];
                    TournamentMatch::where('id', $matchList[$i])->update(['next_match_id' => $nextMatchId]);
                }

                // **Babak Ketiga (Final)**
                $round++;
                $finalMatch = TournamentMatch::create([
                    'tournament_id' => $validated['tournament_id'],
                    'match_category_id' => $validated['match_category_id'],
                    'age_category_id' => $validated['age_category_id'],
                    'round' => $round,
                    'team_member_1_id' => null,
                    'team_member_2_id' => null,
                    'class_id' => $classId
                ]);

                // Update next_match_id untuk semifinal ke final
                TournamentMatch::where('id', $secondRoundMatches[0])->update(['next_match_id' => $finalMatch->id]);
                TournamentMatch::where('id', $secondRoundMatches[1])->update(['next_match_id' => $finalMatch->id]);

            }

            DB::commit(); // Simpan transaksi
            return response()->json(['message' => 'Tournament brackets generated successfully']);
        } catch (\Exception $e) {
            DB::rollBack(); // Batalkan transaksi jika terjadi error
            return response()->json(['error' => $e->getMessage()], 500);
        }
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
