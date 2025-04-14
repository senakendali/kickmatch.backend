<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pool;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use App\Models\MatchSchedule;
use App\Models\MatchScheduleDetail;
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

        // Jika matchChart adalah 2, buat bagan satu babak saja
        if ($matchChart == 2) {
            return $this->generateSingleRoundBracket($poolId, $participants);
        }

        // Jika jumlah peserta 6, tambahkan penanganan khusus
        if ($matchChart == 6) {
            return $this->generateBracketForSix($poolId, $participants);
        }

        // Jika matchChart adalah 0 ("full prestasi"), buat bagan berdasarkan jumlah peserta
        if ($matchChart == 0) {
            return $this->generateFullPrestasiBracket($poolId, $participants);
        }


        return $this->generateSingleElimination($tournamentId, $poolId, $participants, $matchChart);
    }

    public function regenerateBracket($poolId)
    {
        TournamentMatch::where('pool_id', $poolId)->delete();
        
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

        // Jika matchChart adalah 2, buat bagan satu babak saja
        if ($matchChart == 2) {
            return $this->generateSingleRoundBracket($poolId, $participants);
        }

        // Jika jumlah peserta 6, tambahkan penanganan khusus
        if ($matchChart == 6) {
            return $this->generateBracketForSix($poolId, $participants);
        }

        // Jika matchChart adalah 0 ("full prestasi"), buat bagan berdasarkan jumlah peserta
        if ($matchChart == 0) {
            return $this->generateFullPrestasiBracket($poolId, $participants);
        }


        return $this->generateSingleElimination($tournamentId, $poolId, $participants, $matchChart);
    }

    private function generateSingleRoundBracket($poolId, $participants)
    {
        TournamentMatch::where('pool_id', $poolId)->delete();
        
        $matches = collect();
        $matchNumber = 1;
        $totalParticipants = count($participants);

        for ($i = 0; $i < $totalParticipants - 1; $i += 2) {
            $matches->push([
                'pool_id' => $poolId,
                'round' => 1,
                'match_number' => $matchNumber++,
                'participant_1' => $participants[$i]->id,
                'participant_2' => $participants[$i + 1]->id,
                'winner_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Jika jumlah peserta ganjil, peserta terakhir mendapat bye
        if ($totalParticipants % 2 == 1) {
            $matches->push([
                'pool_id' => $poolId,
                'round' => 1,
                'match_number' => $matchNumber++,
                'participant_1' => $participants[$totalParticipants - 1]->id,
                'participant_2' => null,
                'winner_id' => $participants[$totalParticipants - 1]->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        TournamentMatch::insert($matches->toArray());
        
        return response()->json(['message' => 'Bracket single round berhasil dibuat.', 'matches' => $matches]);
    }

    private function generateBracketForSix($poolId, $participants)
    {
        TournamentMatch::where('pool_id', $poolId)->delete();
        
        $matches = collect();
        $matchNumber = 1;

        // Babak pertama: 4 pertandingan
        // Match 1 (Peserta 1 bye - langsung menang)
        $matches->push([
            'pool_id' => $poolId,
            'round' => 1,
            'match_number' => $matchNumber++,
            'participant_1' => $participants[0]->id,
            'participant_2' => null, // Bye
            'winner_id' => $participants[0]->id, // Menang otomatis
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Match 2 (Peserta 2 vs Peserta 3)
        $matches->push([
            'pool_id' => $poolId,
            'round' => 1,
            'match_number' => $matchNumber++,
            'participant_1' => $participants[1]->id,
            'participant_2' => $participants[2]->id,
            'winner_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Match 3 (Peserta 4 vs Peserta 5)
        $matches->push([
            'pool_id' => $poolId,
            'round' => 1,
            'match_number' => $matchNumber++,
            'participant_1' => $participants[3]->id,
            'participant_2' => $participants[4]->id,
            'winner_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Match 4 (Peserta 6 bye - langsung menang)
        $matches->push([
            'pool_id' => $poolId,
            'round' => 1,
            'match_number' => $matchNumber++,
            'participant_1' => $participants[5]->id,
            'participant_2' => null, // Bye
            'winner_id' => $participants[5]->id, // Menang otomatis
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Babak kedua: 2 pemenang dari babak pertama + peserta bye
        // Match 5 (Pemenang Match 2 vs Peserta 1 yang bye di Match 1)
        $matches->push([
            'pool_id' => $poolId,
            'round' => 2,
            'match_number' => $matchNumber++,
            'participant_1' => $participants[0]->id, // Peserta 1 yang menang bye
            'participant_2' => null, // Akan diisi pemenang Match 2
            'winner_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Match 6 (Pemenang Match 3 vs Peserta 6 yang bye di Match 4)
        $matches->push([
            'pool_id' => $poolId,
            'round' => 2,
            'match_number' => $matchNumber++,
            'participant_1' => $participants[5]->id, // Peserta 6 yang menang bye
            'participant_2' => null, // Akan diisi pemenang Match 3
            'winner_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Babak final: Pemenang Match 5 vs Pemenang Match 6
        $matches->push([
            'pool_id' => $poolId,
            'round' => 3,
            'match_number' => $matchNumber++,
            'participant_1' => null, // Akan diisi pemenang Match 5
            'participant_2' => null, // Akan diisi pemenang Match 6
            'winner_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TournamentMatch::insert($matches->toArray());

        return response()->json(['message' => 'Bracket untuk 6 peserta berhasil dibuat.', 'rounds' => $matches]);
    }


    private function generateFullPrestasiBracket($poolId, $participants)
    {
        TournamentMatch::where('pool_id', $poolId)->delete();
        
        $matches = collect();
        $matchNumber = 1;
        $totalParticipants = count($participants);

        // Cari jumlah peserta terdekat yang merupakan pangkat dua
        $powerOfTwo = pow(2, ceil(log($totalParticipants, 2)));
        $byes = $powerOfTwo - $totalParticipants; // Hitung jumlah bye jika jumlah peserta tidak ideal
        
        $round = 1;
        $nextRoundParticipants = collect();
        
        // Babak pertama, tambahkan "bye" jika diperlukan
        $index = 0;
        while ($index < $totalParticipants - $byes) {
            $matches->push([
                'pool_id' => $poolId,
                'round' => $round,
                'match_number' => $matchNumber++,
                'participant_1' => $participants[$index]->id,
                'participant_2' => $participants[$index + 1]->id,
                'winner_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $nextRoundParticipants->push(null); // Slot untuk pemenang
            $index += 2;
        }

        // Tambahkan peserta yang mendapat "bye" langsung ke babak berikutnya
        for ($i = 0; $i < $byes; $i++) {
            $nextRoundParticipants->push($participants[$index + $i]->id);
        }

        // Babak selanjutnya sampai final
        while ($nextRoundParticipants->count() > 1) {
            $round++;
            $newRoundParticipants = collect();

            for ($i = 0; $i < $nextRoundParticipants->count(); $i += 2) {
                $matches->push([
                    'pool_id' => $poolId,
                    'round' => $round,
                    'match_number' => $matchNumber++,
                    'participant_1' => $nextRoundParticipants[$i],
                    'participant_2' => $nextRoundParticipants[$i + 1] ?? null, // Jika ganjil, bye otomatis masuk babak berikutnya
                    'winner_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $newRoundParticipants->push(null); // Slot untuk pemenang
            }

            $nextRoundParticipants = $newRoundParticipants;
        }

        TournamentMatch::insert($matches->toArray());

        return response()->json([
            'message' => 'Bracket full prestasi berhasil dibuat.',
            'rounds' => $matches,
        ]);
    }



    private function generateSingleElimination($tournamentId, $poolId, $participants, $matchChart)
    {
        $totalParticipants = $participants->count();
        $numRounds = ceil(log($matchChart, 2));
        $bracketSize = pow(2, $numRounds);
        $numByes = $bracketSize - $totalParticipants;

        // Hapus pertandingan lama di pool ini
        TournamentMatch::where('pool_id', $poolId)->delete();

        $matches = collect();
        $index = 0;
        $matchNumber = 1;

        if ($matchChart == 6) {
            // 4 peserta bertanding di babak pertama, 2 peserta mendapatkan bye
            for ($i = 0; $i < 2; $i++) {
                $participant1 = $participants[$index++] ?? null;
                $participant2 = $participants[$index++] ?? null;

                $matches->push([
                    'pool_id' => $poolId,
                    'round' => 1,
                    'match_number' => $matchNumber++,
                    'participant_1' => $participant1->id ?? null,
                    'participant_2' => $participant2->id ?? null,
                    'winner_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 2 peserta mendapatkan bye ke babak kedua
            for ($i = 0; $i < 2; $i++) {
                $participant1 = $participants[$index++] ?? null;

                $matches->push([
                    'pool_id' => $poolId,
                    'round' => 2,
                    'match_number' => $matchNumber++,
                    'participant_1' => $participant1->id ?? null,
                    'participant_2' => null,
                    'winner_id' => $participant1->id ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } else {
            // Default untuk bagan lainnya
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

    

    public function listMatches(Request $request, $tournamentId)
    {
        $query = TournamentMatch::with([
                'participantOne:id,name,contingent_id',
                'participantOne.contingent:id,name',
                'participantTwo:id,name,contingent_id',
                'participantTwo.contingent:id,name',
                'winner:id,name,contingent_id',
                'winner.contingent:id,name',
                'pool:id,name,tournament_id'
            ])
            ->whereHas('pool', function ($q) use ($tournamentId) {
                $q->where('tournament_id', $tournamentId);
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('match_schedule_details')
                    ->whereColumn('match_schedule_details.tournament_match_id', 'tournament_matches.id');
            });

        // Optional filters (keep existing)
        if ($request->has('match_category_id')) {
            $query->where('match_category_id', $request->match_category_id);
        }

        if ($request->has('age_category_id')) {
            $query->where('age_category_id', $request->age_category_id);
        }

        if ($request->has('category_class_id')) {
            $query->where('category_class_id', $request->category_class_id);
        }

        if ($request->has('pool_id')) {
            $query->where('pool_id', $request->pool_id);
        }

        $matches = $query->orderBy('round')->orderBy('match_number')->get();

        return response()->json([
            'message' => 'List pertandingan berhasil diambil (excluding already scheduled matches).',
            'data' => $matches
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

    public function allMatches(Request $request, $scheduleId)
    {
        $schedule = MatchSchedule::findOrFail($scheduleId);

        $tournamentId = $schedule->tournament_id;

        // Match yang sudah dijadwalkan dalam schedule ini
        $scheduledMatches = MatchScheduleDetail::where('match_schedule_id', $scheduleId)
            ->pluck('tournament_match_id')
            ->toArray();

        // Ambil semua match (baik yang sudah dijadwalkan di jadwal ini maupun yang belum pernah dijadwalkan)
        $query = TournamentMatch::with([
                'participantOne:id,name,contingent_id',
                'participantOne.contingent:id,name',
                'participantTwo:id,name,contingent_id',
                'participantTwo.contingent:id,name',
                'winner:id,name,contingent_id',
                'winner.contingent:id,name',
                'pool:id,name,tournament_id'
            ])
            ->whereHas('pool', function ($q) use ($tournamentId) {
                $q->where('tournament_id', $tournamentId);
            })
            ->where(function ($q) use ($scheduledMatches) {
                $q->whereIn('id', $scheduledMatches) // match yang udah dijadwalkan di jadwal ini
                ->orWhereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('match_schedule_details')
                        ->whereColumn('match_schedule_details.tournament_match_id', 'tournament_matches.id');
                });
            });

        // Optional filters
        if ($request->has('match_category_id')) {
            $query->where('match_category_id', $request->match_category_id);
        }
        if ($request->has('age_category_id')) {
            $query->where('age_category_id', $request->age_category_id);
        }
        if ($request->has('category_class_id')) {
            $query->where('category_class_id', $request->category_class_id);
        }
        if ($request->has('pool_id')) {
            $query->where('pool_id', $request->pool_id);
        }

        $matches = $query->orderBy('round')->orderBy('match_number')->get();

        return response()->json([
            'message' => 'List pertandingan berhasil diambil (yang belum dijadwalkan + sudah ada di schedule ini).',
            'data' => $matches
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
