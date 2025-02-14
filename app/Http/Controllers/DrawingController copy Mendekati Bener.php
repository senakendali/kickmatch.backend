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
                'team_member_1_name' => $match->participant1->name ?? 'BYE',
                'team_member_2_name' => $match->participant2->name ?? 'BYE',
                'team_member_1_contingent' => $match->participant1->contingent->name ?? 'N/A',
                'team_member_2_contingent' => $match->participant2->contingent->name ?? 'N/A',
                'winner' => $match->winner_id ? $match->winner->name : null,
            ];
        });

        return response()->json([
            'data' => $matchesData
        ]);
    }

    public function store(Request $request)
    {
        // Validasi request
        $validated = $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'match_category_id' => 'required|exists:match_categories,id',
            'age_category_id' => 'required|exists:age_categories,id',
        ]);

        // Ambil semua kelas yang memiliki peserta
        $classes = TeamMember::select('category_class_id as class_id')
            ->distinct()
            ->pluck('class_id');

        foreach ($classes as $classId) {
            // Ambil peserta berdasarkan kelas & acak urutannya
            $participants = TeamMember::where('category_class_id', $classId)->get()->shuffle();
            $totalParticipants = $participants->count();

            // Jika peserta kurang dari 2, skip kelas ini
            if ($totalParticipants < 2) {
                continue;
            }

            // Cari pangkat 2 terdekat untuk menentukan jumlah pertandingan knockout
            $nextPowerOfTwo = pow(2, ceil(log($totalParticipants, 2)));
            $byes = $nextPowerOfTwo - $totalParticipants;

            // **Pisahkan 2 peserta untuk bertanding di babak pertama jika jumlah peserta bukan kelipatan 2**
            $firstRoundParticipants = collect();
            if ($totalParticipants % 2 !== 0) {
                $firstRoundParticipants = $participants->splice(0, 2);
            }

            // **Peserta yang tersisa otomatis masuk ke babak kedua**
            $remainingParticipants = $participants;

            // **Simpan semua pertandingan**
            $round = 1;
            $previousRoundMatches = [];

            // **Babak 1: Pertandingan pertama jika ada peserta ganjil**
            if ($firstRoundParticipants->count() === 2) {
                $p1 = $firstRoundParticipants->pop();
                $p2 = $firstRoundParticipants->pop();

                $match = TournamentMatch::create([
                    'tournament_id' => $validated['tournament_id'],
                    'match_category_id' => $validated['match_category_id'],
                    'age_category_id' => $validated['age_category_id'],
                    'round' => $round,
                    'team_member_1_id' => $p1->id,
                    'team_member_2_id' => $p2->id,
                    'class_id' => $classId
                ]);

                // Pemenang dari babak pertama masuk ke babak kedua
                $remainingParticipants->push((object) ['match_id' => $match->id, 'id' => null]);
            }

            // **Naik ke Babak 2: Semua peserta tersisa masuk ke babak ini**
            $round++;
            $matchCount = $remainingParticipants->count() / 2;
            $previousRoundMatches = [];
            $currentRoundMatches = [];

            while ($matchCount > 0) {
                $newRoundParticipants = collect();

                while ($remainingParticipants->count() > 1) {
                    $p1 = $remainingParticipants->pop();
                    $p2 = $this->findOpponent($remainingParticipants, $p1);

                    if (!$p1) continue;

                    // **Buat pertandingan**
                    $match = TournamentMatch::create([
                        'tournament_id' => $validated['tournament_id'],
                        'match_category_id' => $validated['match_category_id'],
                        'age_category_id' => $validated['age_category_id'],
                        'round' => $round,
                        'team_member_1_id' => $p1->id,
                        'team_member_2_id' => $p2->id ?? null,
                        'class_id' => $classId
                    ]);

                    $currentRoundMatches[] = $match;

                    // Simpan referensi untuk babak berikutnya
                    $newRoundParticipants->push((object) ['match_id' => $match->id, 'id' => null]);
                }

                // Perbarui daftar peserta untuk babak berikutnya
                $remainingParticipants = $newRoundParticipants;
                $previousRoundMatches = $currentRoundMatches;
                $matchCount /= 2;
                $round++;
            }
        }

        return response()->json(['message' => 'Tournament brackets generated successfully']);
    }

    /**
     * Mencari lawan dengan tim yang berbeda
     */
    private function findOpponent($participants, $p1)
    {
        foreach ($participants as $key => $p2) {
            if (isset($p1->contingent_id) && isset($p2->contingent_id)) {
                if ($p2->contingent_id !== $p1->contingent_id) {
                    return $participants->splice($key, 1)->first();
                }
            }
        }
        return $participants->pop();
    }







    





    public function generateBracket($tournamentId, $matchCategoryId, $ageCategoryId)
    {
       
        // Ambil semua pertandingan yang sudah ada berdasarkan tournament_id, match_category_id, dan age_category_id
        $matches = TournamentMatch::with(['participant1', 'participant2', 'participant1.contingent', 'participant2.contingent'])->where('tournament_id', $tournamentId)
            ->where('match_category_id', $matchCategoryId)
            ->where('age_category_id', $ageCategoryId)
            ->orderBy('round')
            ->get();

        // Kelompokkan pertandingan berdasarkan round
        $bracket = [];
        foreach ($matches as $match) {
            $bracket[$match->round][] = $match;
        }

        // Tentukan pemenang dari pertandingan untuk digunakan di ronde selanjutnya
        $winners = [];

        foreach ($bracket as $round => $matchesInRound) {
            // Tentukan pemenang untuk setiap pertandingan
            foreach ($matchesInRound as $match) {
                // Cari pemenang berdasarkan winner_id
                $winnerId = $match->winner_id;

                // Jika pemenang adalah peserta BYE, otomatis pemenangnya adalah peserta yang tidak mendapat BYE
                if (!$winnerId && str_starts_with($match->team_member_1_id, 'BYE')) {
                    $winnerId = $match->team_member_2_id;
                } elseif (!$winnerId && str_starts_with($match->team_member_2_id, 'BYE')) {
                    $winnerId = $match->team_member_1_id;
                }

                // Masukkan pemenang ke dalam array winners untuk ronde berikutnya
                $winners[$round + 1][] = $winnerId;
            }
        }

        // Menyusun final bracket (babak final)
        $finalMatch = $this->buildFinalBracket($winners);

        return response()->json([
            'bracket' => $bracket,
            'winners' => $winners,
            'finalMatch' => $finalMatch
        ]);
    }

    private function buildFinalBracket($winners)
    {
        // Cari pemenang untuk final match
        $finalParticipants = [];
        
        // Ambil pemenang dari ronde terakhir
        if (isset($winners)) {
            $finalParticipants = array_slice($winners, -2); // Ambil 2 pemenang terakhir untuk final
        }

        // Kembalikan data untuk final match
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
