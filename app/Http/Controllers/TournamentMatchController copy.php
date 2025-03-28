<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pool;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use Illuminate\Support\Facades\DB;

class TournamentMatchController extends Controller
{
    public function generateBracket($poolId)
    {
        // Ambil pool untuk mendapatkan tournament_id dan match_chart
        $pool = Pool::find($poolId);
        if (!$pool) {
            return response()->json(['message' => 'Pool tidak ditemukan.'], 404);
        }

        $tournamentId = $pool->tournament_id;
        $matchChart = (int) $pool->match_chart; // Jenis bagan (2, 4, 6, 8, 16)

        // Ambil peserta yang belum masuk di tournament_matches
        $existingMatches = TournamentMatch::where('pool_id', $poolId)->pluck('participant_1')->merge(
            TournamentMatch::where('pool_id', $poolId)->pluck('participant_2')
        )->unique();

        $participants = DB::table('tournament_participants')
            ->join('team_members', 'tournament_participants.team_member_id', '=', 'team_members.id')
            ->where('tournament_participants.tournament_id', $tournamentId)
            ->whereNotIn('team_members.id', $existingMatches) // Hanya yang belum masuk match
            ->select('team_members.id', 'team_members.name')
            ->get()
            ->shuffle();

        if ($participants->isEmpty()) {
            return response()->json(['message' => 'Semua peserta sudah memiliki match.'], 400);
        }

        // Pastikan jumlah peserta sesuai match_chart
        if ($participants->count() > $matchChart) {
            $participants = $participants->take($matchChart);
        } elseif ($participants->count() < $matchChart) {
            return response()->json([
                'message' => "Jumlah peserta kurang dari $matchChart, tidak bisa membuat bagan."
            ], 400);
        }

        return $this->generateSingleElimination($tournamentId, $poolId, $participants);
    }

    private function generateSingleElimination($tournamentId, $poolId, $participants)
    {
        $totalParticipants = $participants->count();
        $numRounds = ceil(log($totalParticipants, 2));
        $bracketSize = pow(2, $numRounds);
        $numByes = $bracketSize - $totalParticipants;

        // Hapus pertandingan lama di pool ini
        TournamentMatch::where('pool_id', $poolId)->delete();

        $matches = collect();
        $index = 0;

        $matchNumber = 1;

        for ($i = 0; $i < $bracketSize / 2; $i++) {
            $participant1 = $participants[$index] ?? null;
            $participant2 = ($i < $numByes) ? null : ($participants[++$index] ?? null);
            $index++;

            $matches->push([
                'pool_id' => $poolId,
                'round' => 1,
                'match_number' => $matchNumber++,
                'participant_1' => $participant1->id ?? null,
                'participant_2' => $participant2->id ?? null,
                'winner_id' => $participant2 === null ? $participant1->id : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        TournamentMatch::insert($matches->toArray());

        return response()->json(['message' => 'Bracket berhasil dibuat.', 'rounds' => $matches]);
    }

    public function dummy($poolId)
    {
        return response()->json([
            "rounds" => [
                // Quarter-finals
                [
                    "games" => [
                        ["player1" => ["id" => "1", "name" => "Competitor 1", "winner" => true], "player2" => ["id" => "2", "name" => "Competitor 2", "winner" => false]],
                        ["player1" => ["id" => "3", "name" => "Competitor 3", "winner" => false], "player2" => ["id" => "4", "name" => "Competitor 4", "winner" => false]],
                        ["player1" => ["id" => "5", "name" => "Competitor 5", "winner" => true], "player2" => ["id" => "6", "name" => "Competitor 6", "winner" => false]],
                        ["player1" => ["id" => "7", "name" => "Competitor 7", "winner" => false], "player2" => ["id" => "8", "name" => "Competitor 8", "winner" => false]],
                    ]
                ],
                // Semi-finals
                [
                    "games" => [
                        ["player1" => ["id" => null, "name" => "TBD", "winner" => false], "player2" => ["id" => null, "name" => "TBD", "winner" => false]],
                        ["player1" => ["id" => null, "name" => "TBD", "winner" => false], "player2" => ["id" => null, "name" => "TBD", "winner" => false]],
                    ]
                ],
                // Final
                [
                    "games" => [
                        ["player1" => ["id" => null, "name" => "TBD", "winner" => false], "player2" => ["id" => null, "name" => "TBD", "winner" => false]],
                    ]
                ]
            ]
        ]);
    }

    public function getMatches($poolId)
    {
        // Ambil nilai match_chart dari tabel pools
        $pool = Pool::findOrFail($poolId);
        $matchChart = (int) $pool->match_chart; // Jenis bagan (2, 4, 6, 8, 16)
        
        // Ambil semua pertandingan berdasarkan pool_id
        $matches = TournamentMatch::where('pool_id', $poolId)
            ->with(['participantOne', 'participantTwo', 'winner'])
            ->orderBy('round')
            ->get();

        $groupedRounds = [];
        $allGamesEmpty = true; // Flag untuk mengecek apakah semua games masih kosong

        // Tentukan jumlah total babak berdasarkan match_chart
        $totalRounds = log($matchChart, 2);

        // Inisialisasi babak sesuai dengan match_chart
        for ($round = 1; $round <= $totalRounds; $round++) {
            $groupedRounds[$round] = [
                'games' => []
            ];
        }

        // Kelompokkan pertandingan berdasarkan round
        foreach ($matches as $match) {
            $round = $match->round; // Babak pertandingan

            if (!isset($groupedRounds[$round])) {
                $groupedRounds[$round] = [
                    'games' => []
                ];
            }

            $gameData = [
                'player1' => $match->participantOne ? [
                    'id' => (string) $match->participantOne->id,
                    'name' => $match->participantOne->name,
                    'winner' => $match->winner && $match->winner->id == $match->participantOne->id
                ] : ['id' => null, 'name' => 'TBD', 'winner' => false],

                'player2' => $match->participantTwo ? [
                    'id' => (string) $match->participantTwo->id,
                    'name' => $match->participantTwo->name,
                    'winner' => $match->winner && $match->winner->id == $match->participantTwo->id
                ] : ['id' => null, 'name' => 'TBD', 'winner' => false],
            ];

            // Jika setidaknya ada satu pertandingan yang memiliki data pemain, set flag ke false
            if ($match->participantOne || $match->participantTwo) {
                $allGamesEmpty = false;
            }

            $groupedRounds[$round]['games'][] = $gameData;
        }

        // Tambahkan game kosong (TBD) jika jumlah pertandingan belum cukup di babak tertentu
        for ($round = 1; $round <= $totalRounds; $round++) {
            $expectedGames = $matchChart / pow(2, $round); // Jumlah game di setiap babak

            while (count($groupedRounds[$round]['games']) < $expectedGames) {
                $groupedRounds[$round]['games'][] = [
                    'player1' => [
                        'id' => null,
                        'name' => 'TBD',
                        'winner' => false
                    ],
                    'player2' => [
                        'id' => null,
                        'name' => 'TBD',
                        'winner' => false
                    ]
                ];
            }
        }

        // Susun ulang hasil dalam bentuk array numerik
        $rounds = array_values($groupedRounds);

        // Tentukan status berdasarkan apakah semua game masih kosong atau tidak
        $status = $allGamesEmpty ? "pending" : "ongoing";

        return response()->json([
            'rounds' => $rounds,
            'match_chart' => $matchChart,
            'status' => $status
        ]);
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
                        //'team_member_1_contingent' => "TBD",
//'team_member_2_contingent' => "TBD",
                        'winner' => "TBD",
                    ];
                }
                $bracket[$round] = $nextRoundMatches;
            }
        }

        return $bracket;
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

    private function buildFinalBracket($winners)
    {
        $finalParticipants = array_slice($winners, -2);

        return [
            'final_match' => [
                'participants' => $finalParticipants
            ]
        ];
    }

    private function getParticipantName($participantId)
    {
        if ($participantId === "TBD") return "TBD";

        $participant = TournamentParticipant::find($participantId);
        return $participant ? $participant->name : "TBD";
    }



}
