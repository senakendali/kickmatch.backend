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

            // Cari pangkat 2 terdekat
            $nextPowerOfTwo = pow(2, ceil(log($totalParticipants, 2)));
            $byes = $nextPowerOfTwo - $totalParticipants;

            // Tambahkan BYE ke peserta
            for ($i = 0; $i < $byes; $i++) {
                $participants->push((object) [
                    'id' => "BYE_$i", // ID unik untuk BYE
                    'name' => 'BYE',
                    'contingent_id' => null // Pastikan ada contingent_id
                ]);
            }

            // Inisialisasi pertandingan
            $round = 1;
            $matchCount = $nextPowerOfTwo / 2;

            while ($matchCount > 0) {
                $newRoundParticipants = collect();

                while ($participants->count() > 1) {
                    $p1 = $participants->pop();
                    $p2 = $this->findOpponent($participants, $p1);

                    // **Cek apakah peserta ada dan memiliki ID valid**
                    if (!$p1 || !isset($p1->id)) {
                        \Log::error("Peserta 1 tidak valid: ", (array) $p1);
                        continue;
                    }

                    if (!$p2) {
                        \Log::error("Peserta 2 tidak valid: ", (array) $p2);
                    }

                    // **Gunakan NULL untuk peserta BYE dalam database**
                    $p1_id = is_numeric($p1->id) ? $p1->id : null;
                    $p2_id = $p2 && is_numeric($p2->id) ? $p2->id : null;

                    // Jika kedua peserta null, skip pertandingan ini
                    if ($p1_id === null && $p2_id === null) {
                        \Log::error("Match skipped: p1 and p2 are null.");
                        continue;
                    }

                    // **Pastikan p1 selalu memiliki ID**
                    if ($p1_id === null) {
                        \Log::error("Skipping match because p1_id is null.");
                        continue;
                    }

                    // Simpan pertandingan
                    $match = TournamentMatch::create([
                        'tournament_id' => $validated['tournament_id'],
                        'match_category_id' => $validated['match_category_id'],
                        'age_category_id' => $validated['age_category_id'],
                        'round' => $round,
                        'team_member_1_id' => $p1_id,
                        'team_member_2_id' => $p2_id,
                        'class_id' => $classId
                    ]);

                    // Jika lawan adalah BYE, langsung menang
                    if ($p2 && str_starts_with($p2->id, "BYE")) {
                        $match->update(['winner_id' => $p1_id]);
                        $newRoundParticipants->push($p1);
                    } else {
                        $newRoundParticipants->push((object) ['match_id' => $match->id]);
                    }

                    // Jika p1 adalah BYE, langsung menang
                    if (str_starts_with($p1->id, "BYE")) {
                        $match->update(['winner_id' => $p2_id]);
                        $newRoundParticipants->push($p2);
                    }
                }

                $participants = $newRoundParticipants;
                $round++;
                $matchCount /= 2;
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
            // Pastikan objek memiliki properti contingent_id sebelum diakses
            if (isset($p1->contingent_id) && isset($p2->contingent_id)) {
                if ($p2->contingent_id !== $p1->contingent_id) {
                    return $participants->splice($key, 1)->first();
                }
            } else {
                \Log::warning("Peserta tidak memiliki contingent_id", ['p1' => $p1, 'p2' => $p2]);
            }
        }

        return $participants->pop();
    }

    
    public function show($id)
    {
        
    }

    public function update(Request $request, $id)
    {
        
    }

    public function destroy($id)
    {
        
    }

   
}
