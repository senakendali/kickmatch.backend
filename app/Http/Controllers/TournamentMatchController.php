<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pool;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use App\Models\MatchSchedule;
use App\Models\MatchScheduleDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;


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

        $pool = Pool::with(['categoryClass'])->find($poolId);
        if (!$pool) {
            return response()->json(['message' => 'Pool tidak ditemukan.'], 404);
        }

        $tournamentId = $pool->tournament_id;
        $matchChart = (int) $pool->match_chart;

        // ✅ Ambil peserta yang memang sudah punya pool_id = $poolId
        $participants = collect(
            DB::table('tournament_participants')
                ->join('team_members', 'tournament_participants.team_member_id', '=', 'team_members.id')
                ->where('tournament_participants.tournament_id', $tournamentId)
                ->where('tournament_participants.pool_id', $poolId)
                ->select('team_members.id', 'team_members.name')
                ->get()
        )->shuffle()->values();

       




        /*if ($participants->isEmpty()) {
            return response()->json(['message' => 'Tidak ada peserta sesuai pool ini yang belum dipakai.'], 400);
        }*/

        // ✅ Validasi peserta cukup
        $participantCount = $participants->count();
        if (!in_array($matchChart, ['full_prestasi', 0]) && $participantCount < $matchChart) {
            return response()->json([
                'message' => 'Peserta tidak mencukupi untuk membuat bagan ini.',
                'found' => $participantCount,
                'needed' => $matchChart
            ], 400);
        }

        // 🧠 Generate berdasarkan match chart
        if ($matchChart === 2) {
            return $this->generateSingleRoundBracket($poolId, $participants);
        }

        if ($matchChart === 6) {
            return $this->generateBracketForSix($poolId, $participants);
        }

        if ($matchChart === 0) {
            return $this->generateFullPrestasiBracket($poolId, $participants);
        }

        return $this->generateSingleElimination($tournamentId, $poolId, $participants, $matchChart);
    }


    public function regenerateBracket_($poolId)
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

    private function generateSingleRoundBracket($poolId)
    {
        // Hapus match sebelumnya di pool ini
        TournamentMatch::where('pool_id', $poolId)->delete();

        // Ambil info pool & tournament
        $pool = Pool::with('tournament')->find($poolId);
        if (!$pool) {
            return response()->json(['message' => 'Pool tidak ditemukan.'], 404);
        }

        // 🔍 Debug log pool info
        \Log::debug('🎯 Pool Info', [
            'pool_id' => $poolId,
            'tournament_id' => $pool->tournament_id,
            'age_category_id' => $pool->age_category_id,
        ]);

        // Ambil peserta yang valid
        $participants = DB::table('team_members')
            ->join('tournament_participants', 'team_members.id', '=', 'tournament_participants.team_member_id')
            ->where('tournament_participants.tournament_id', $pool->tournament_id)
            //->where('tournament_participants.pool_id', $poolId) // ⛔ sementara di-nonaktifkan
            ->where('team_members.age_category_id', $pool->age_category_id)
            ->whereIn('team_members.contingent_id', function ($q) use ($pool) {
                $q->select('contingent_id')
                ->from('tournament_contingents')
                ->where('tournament_id', $pool->tournament_id);
            })
            ->select(
                'team_members.id',
                'team_members.category_class_id',
                'team_members.gender',
                'team_members.contingent_id'
            )
            ->get();

        // 🔍 Debug peserta yang ditemukan
        \Log::debug('🧪 Peserta ditemukan:', [
            'count' => $participants->count(),
            'ids' => $participants->pluck('id')
        ]);

        if ($participants->isEmpty()) {
            return response()->json(['message' => 'Tidak ada peserta valid untuk pool ini.'], 400);
        }

        $matches = collect();
        $matchNumber = 1;

        // Group peserta berdasarkan class + gender
        $grouped = $participants->groupBy(function ($p) {
            return ($p->category_class_id ?? 'null') . '-' . ($p->gender ?? 'null');
        });

        foreach ($grouped as $group) {
            $queue = $group->shuffle()->values();

            while ($queue->count() > 0) {
                $p1 = $queue->shift();

                // Cari lawan beda tim dan bukan diri sendiri
                $opponentIndex = $queue->search(fn($p2) =>
                    $p2->contingent_id !== $p1->contingent_id &&
                    $p2->id !== $p1->id
                );

                $p2 = $opponentIndex !== false ? $queue->pull($opponentIndex) : null;

                $matches->push([
                    'pool_id' => $poolId,
                    'round' => 1,
                    'match_number' => $matchNumber++,
                    'participant_1' => $p1->id,
                    'participant_2' => $p2?->id,
                    'winner_id' => $p2 ? null : $p1->id,
                    'next_match_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        TournamentMatch::insert($matches->toArray());

        return response()->json([
            'message' => 'Bracket berhasil dibuat dengan debug filter usia & contingent.',
            'total_participant' => $participants->count(),
            'total_match' => $matches->count(),
            'matches' => $matches,
        ]);
    }






    private function generateBracketForSix($poolId, $participants)
    {
        TournamentMatch::where('pool_id', $poolId)->delete();

        $pool = Pool::with('tournament')->find($poolId);
        if (!$pool) {
            return response()->json(['message' => 'Pool tidak ditemukan.'], 404);
        }

        $usedParticipantIds = TournamentMatch::whereHas('pool', function ($q) use ($pool) {
            $q->where('tournament_id', $pool->tournament_id);
        })
        ->pluck('participant_1')
        ->merge(
            TournamentMatch::whereHas('pool', function ($q) use ($pool) {
                $q->where('tournament_id', $pool->tournament_id);
            })
            ->pluck('participant_2')
        )->unique();

        $participants = $participants->reject(function ($p) use ($usedParticipantIds) {
            return $usedParticipantIds->contains($p->id);
        })->values();

        if ($participants->count() < 6) {
            return response()->json([
                'message' => 'Peserta kurang dari 6 setelah disaring dari pool lain.',
            ], 400);
        }

        // 🔁 Ambil hanya 6 peserta pertama
        $selected = $participants->slice(0, 6)->values();
        $participantIds = $selected->pluck('id')->toArray();

        // ✅ Update pool_id hanya untuk peserta yang dipakai
        TournamentParticipant::whereIn('team_member_id', $participantIds)
            ->where('tournament_id', $pool->tournament_id)
            ->update(['pool_id' => $poolId]);

        $matches = collect();
        $matchNumber = 1;

        $match1Id = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId,
            'round' => 1,
            'match_number' => $matchNumber++,
            'participant_1' => $selected[0]->id,
            'participant_2' => null,
            'winner_id' => $selected[0]->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $match2Id = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId,
            'round' => 1,
            'match_number' => $matchNumber++,
            'participant_1' => $selected[1]->id,
            'participant_2' => $selected[2]->id,
            'winner_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $match3Id = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId,
            'round' => 1,
            'match_number' => $matchNumber++,
            'participant_1' => $selected[3]->id,
            'participant_2' => $selected[4]->id,
            'winner_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $match4Id = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId,
            'round' => 1,
            'match_number' => $matchNumber++,
            'participant_1' => $selected[5]->id,
            'participant_2' => null,
            'winner_id' => $selected[5]->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $match5Id = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId,
            'round' => 2,
            'match_number' => $matchNumber++,
            'participant_1' => $selected[0]->id,
            'participant_2' => null,
            'winner_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $match6Id = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId,
            'round' => 2,
            'match_number' => $matchNumber++,
            'participant_1' => $selected[5]->id,
            'participant_2' => null,
            'winner_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $match7Id = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId,
            'round' => 3,
            'match_number' => $matchNumber++,
            'participant_1' => null,
            'participant_2' => null,
            'winner_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tournament_matches')->where('id', $match1Id)->update(['next_match_id' => $match5Id]);
        DB::table('tournament_matches')->where('id', $match2Id)->update(['next_match_id' => $match5Id]);
        DB::table('tournament_matches')->where('id', $match3Id)->update(['next_match_id' => $match6Id]);
        DB::table('tournament_matches')->where('id', $match4Id)->update(['next_match_id' => $match6Id]);
        DB::table('tournament_matches')->where('id', $match5Id)->update(['next_match_id' => $match7Id]);
        DB::table('tournament_matches')->where('id', $match6Id)->update(['next_match_id' => $match7Id]);

        $inserted = TournamentMatch::where('pool_id', $poolId)
            ->orderBy('round')
            ->orderBy('match_number')
            ->get();

        return response()->json([
            'message' => 'Bracket untuk 6 peserta berhasil dibuat.',
            'rounds' => $inserted,
        ]);
    }

    private function generateFullPrestasiBracket__($poolId, $participants)
    {
        TournamentMatch::where('pool_id', $poolId)->delete();

        $pool = Pool::with('tournament')->find($poolId);
        if (!$pool) {
            return response()->json(['message' => 'Pool tidak ditemukan.'], 404);
        }

        $usedParticipantIds = TournamentMatch::whereHas('pool', function ($q) use ($pool) {
            $q->where('tournament_id', $pool->tournament_id);
        })
        ->pluck('participant_1')
        ->merge(
            TournamentMatch::whereHas('pool', function ($q) use ($pool) {
                $q->where('tournament_id', $pool->tournament_id);
            })
            ->pluck('participant_2')
        )->unique();

        $participants = $participants->reject(function ($p) use ($usedParticipantIds) {
            return $usedParticipantIds->contains($p->id);
        })->values();

        if ($participants->isEmpty()) {
            return response()->json([
                'message' => 'Semua peserta sudah memiliki match di pool lain.',
            ], 400);
        }

        $totalParticipants = $participants->count();
        $bracketSize = pow(2, ceil(log($totalParticipants, 2)));
        $totalRounds = log($bracketSize, 2);
        $matchNumber = 1;

        $matches = collect();

        for ($round = 1; $round <= $totalRounds; $round++) {
            $matchCount = $bracketSize / pow(2, $round);
            for ($i = 0; $i < $matchCount; $i++) {
                $matches->push([
                    'pool_id' => $poolId,
                    'round' => $round,
                    'match_number' => $matchNumber++,
                    'participant_1' => null,
                    'participant_2' => null,
                    'winner_id' => null,
                    'next_match_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('tournament_matches')->insert($matches->toArray());

        $allMatches = TournamentMatch::where('pool_id', $poolId)
            ->orderBy('round')
            ->orderBy('match_number')
            ->get();

        $byRound = $allMatches->groupBy('round');

        // Hubungkan next_match_id
        foreach ($byRound as $round => $roundMatches) {
            if (isset($byRound[$round + 1])) {
                $nextMatches = $byRound[$round + 1];
                foreach ($roundMatches as $i => $match) {
                    $parentIndex = floor($i / 2);
                    $match->next_match_id = $nextMatches[$parentIndex]->id ?? null;
                    $match->save();
                }
            }
        }

        // Ambil peserta hanya sebanyak yang dibutuhkan
        $firstRound = $byRound[1];
        $maxParticipantCount = $firstRound->count() * 2;
        $selectedParticipants = $participants->slice(0, $maxParticipantCount)->values();
        $selectedIds = $selectedParticipants->pluck('id')->toArray();

        // Update pool_id hanya untuk yang digunakan
        TournamentParticipant::whereIn('team_member_id', $selectedIds)
            ->where('tournament_id', $pool->tournament_id)
            ->update(['pool_id' => $poolId]);

        // Assign peserta ke babak pertama
        $i = 0;
        foreach ($firstRound as $match) {
            $match->participant_1 = $selectedIds[$i++] ?? null;
            $match->participant_2 = $selectedIds[$i++] ?? null;

            if ($match->participant_1 && !$match->participant_2) {
                $match->winner_id = $match->participant_1;
            }

            $match->save();
        }

        return response()->json([
            'message' => 'Bracket full prestasi berhasil dibuat (dengan struktur lengkap).',
            'rounds' => $allMatches,
        ]);
    }

   
private function generateFullPrestasiBracket($poolId, $participants)
{
    TournamentMatch::where('pool_id', $poolId)->delete();

    $pool = Pool::with('tournament')->find($poolId);
    if (!$pool) {
        return response()->json(['message' => 'Pool tidak ditemukan.'], 404);
    }

    $tournamentId = $pool->tournament_id;
    $desiredClassId = $pool->category_class_id;
    $desiredMatchCategoryId = $pool->match_category_id;

    if (!$desiredClassId || !$desiredMatchCategoryId) {
        return response()->json(['message' => 'Pool tidak memiliki kelas atau kategori pertandingan.'], 400);
    }

    $eligibleParticipants = DB::table('tournament_participants')
        ->join('team_members', 'tournament_participants.team_member_id', '=', 'team_members.id')
        ->where('tournament_participants.tournament_id', $tournamentId)
        ->where('team_members.category_class_id', $desiredClassId)
        ->where('team_members.match_category_id', $desiredMatchCategoryId)
        ->select('tournament_participants.id as tp_id', 'team_members.id as id', 'team_members.name')
        ->get();

    if ($eligibleParticipants->count() === 6) {
        return $this->generateBracketForSix($poolId, $eligibleParticipants);
    }

    if ($eligibleParticipants->count() === 10) {
        return $this->generateBracketForTen($poolId, $eligibleParticipants);
    }

    // lanjut pakai skema general seperti sebelumnya
    DB::table('tournament_participants')
        ->where('pool_id', $poolId)
        ->update(['pool_id' => null]);

    DB::table('tournament_participants')
        ->join('team_members', 'tournament_participants.team_member_id', '=', 'team_members.id')
        ->where('tournament_participants.tournament_id', $tournamentId)
        ->where('team_members.category_class_id', $desiredClassId)
        ->where('team_members.match_category_id', $desiredMatchCategoryId)
        ->update(['tournament_participants.pool_id' => null]);

    $shuffled = $eligibleParticipants->shuffle()->values();
    $participantIdsToUpdate = $shuffled->pluck('tp_id');

    \Log::info("\u2705 Peserta masuk pool {$poolId}", [
        'total' => $participantIdsToUpdate->count(),
        'nama' => $shuffled->pluck('name'),
    ]);

    DB::table('tournament_participants')
        ->whereIn('id', $participantIdsToUpdate)
        ->update(['pool_id' => $poolId]);

    $participantIds = $shuffled->pluck('id')->shuffle()->values();
    $total = $participantIds->count();
    $bracketSize = pow(2, ceil(log($total, 2)));
    $rounds = ceil(log($bracketSize, 2));
    $matchNumber = 1;
    $matchRefs = [];

    for ($round = 1; $round <= $rounds; $round++) {
        $numMatches = $bracketSize / pow(2, $round);
        for ($i = 0; $i < $numMatches; $i++) {
            $matchRefs[$matchNumber] = [
                'pool_id' => $poolId,
                'round' => $round,
                'match_number' => $matchNumber,
                'participant_1' => null,
                'participant_2' => null,
                'winner_id' => null,
                'next_match_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $matchNumber++;
        }
    }

    $i = 0;
    foreach ($matchRefs as $number => &$match) {
        if ($match['round'] !== 1) continue;

        $match['participant_1'] = $participantIds[$i++] ?? null;
        $match['participant_2'] = $participantIds[$i++] ?? null;

        if ($match['participant_1'] && !$match['participant_2']) {
            $match['winner_id'] = $match['participant_1'];
        } elseif (!$match['participant_1'] && $match['participant_2']) {
            $match['winner_id'] = $match['participant_2'];
        }
    }
    unset($match);

    foreach ($matchRefs as $number => $match) {
        if ($match['round'] === 1 && is_null($match['participant_1']) && is_null($match['participant_2'])) {
            unset($matchRefs[$number]);
        }
    }

    $roundGrouped = collect($matchRefs)->groupBy('round');
    foreach ($roundGrouped as $r => $group) {
        if (isset($roundGrouped[$r + 1])) {
            $nextMatches = $roundGrouped[$r + 1]->values();
            foreach ($group->values() as $i => $match) {
                $matchRefs[$match['match_number']]['next_match_number'] = $nextMatches[floor($i / 2)]['match_number'] ?? null;
            }
        }
    }

    $matchesToInsert = array_map(function ($match) {
        unset($match['next_match_id']);
        return Arr::except($match, ['next_match_number']);
    }, array_values($matchRefs));

    DB::table('tournament_matches')->insert($matchesToInsert);

    $matchMap = TournamentMatch::where('pool_id', $poolId)->get()->keyBy('match_number');

    foreach ($matchRefs as $matchData) {
        if (isset($matchData['next_match_number'])) {
            $match = $matchMap[$matchData['match_number']] ?? null;
            $nextMatch = $matchMap[$matchData['next_match_number']] ?? null;

            if ($match && $nextMatch) {
                $match->next_match_id = $nextMatch->id;
                $match->save();

                if ($match->winner_id && is_null($nextMatch->participant_1)) {
                    $nextMatch->participant_1 = $match->winner_id;
                    $nextMatch->save();
                } elseif ($match->winner_id && is_null($nextMatch->participant_2)) {
                    $nextMatch->participant_2 = $match->winner_id;
                    $nextMatch->save();
                }
            }
        }
    }

    return response()->json([
        'message' => 'Bracket berhasil dibuat dan peserta sudah di-assign ke pool.',
        'total_participants' => $total,
        'bracket_size' => $bracketSize,
        'total_matches' => count($matchRefs),
        'rounds_generated' => $rounds,
    ]);
}

private function generateBracketForTen($poolId, $participants)
{
    TournamentMatch::where('pool_id', $poolId)->delete();

    $pool = Pool::with('tournament')->find($poolId);
    if (!$pool) {
        return response()->json(['message' => 'Pool tidak ditemukan.'], 404);
    }

    $usedParticipantIds = TournamentMatch::whereHas('pool', function ($q) use ($pool) {
        $q->where('tournament_id', $pool->tournament_id);
    })
        ->pluck('participant_1')
        ->merge(
            TournamentMatch::whereHas('pool', function ($q) use ($pool) {
                $q->where('tournament_id', $pool->tournament_id);
            })
            ->pluck('participant_2')
        )->unique();

    $participants = $participants->reject(function ($p) use ($usedParticipantIds) {
        return $usedParticipantIds->contains($p->id);
    })->values();

    if ($participants->count() < 10) {
        return response()->json(['message' => 'Peserta kurang dari 10 setelah disaring.'], 400);
    }

    $selected = $participants->slice(0, 10)->values();
    $participantIds = $selected->pluck('id')->toArray();

    TournamentParticipant::whereIn('team_member_id', $participantIds)
        ->where('tournament_id', $pool->tournament_id)
        ->update(['pool_id' => $poolId]);

    $matchNumber = 1;
    $now = now();
    $matchIds = [];

    // ROUND 1 - 6 MATCH (2 bye: M1 & M6)
    $matchIds[] = DB::table('tournament_matches')->insertGetId([
        'pool_id' => $poolId, 'round' => 1, 'match_number' => $matchNumber++,
        'participant_1' => $selected[0]->id, 'participant_2' => null, 'winner_id' => $selected[0]->id,
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $matchIds[] = DB::table('tournament_matches')->insertGetId([
        'pool_id' => $poolId, 'round' => 1, 'match_number' => $matchNumber++,
        'participant_1' => $selected[1]->id, 'participant_2' => $selected[2]->id,
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $matchIds[] = DB::table('tournament_matches')->insertGetId([
        'pool_id' => $poolId, 'round' => 1, 'match_number' => $matchNumber++,
        'participant_1' => $selected[3]->id, 'participant_2' => $selected[4]->id,
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $matchIds[] = DB::table('tournament_matches')->insertGetId([
        'pool_id' => $poolId, 'round' => 1, 'match_number' => $matchNumber++,
        'participant_1' => $selected[5]->id, 'participant_2' => $selected[6]->id,
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $matchIds[] = DB::table('tournament_matches')->insertGetId([
        'pool_id' => $poolId, 'round' => 1, 'match_number' => $matchNumber++,
        'participant_1' => $selected[7]->id, 'participant_2' => $selected[8]->id,
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $matchIds[] = DB::table('tournament_matches')->insertGetId([
        'pool_id' => $poolId, 'round' => 1, 'match_number' => $matchNumber++,
        'participant_1' => $selected[9]->id, 'participant_2' => null, 'winner_id' => $selected[9]->id,
        'created_at' => $now, 'updated_at' => $now,
    ]);

    // ROUND 2 - 4 MATCH
    $matchIds[] = DB::table('tournament_matches')->insertGetId([
        'pool_id' => $poolId, 'round' => 2, 'match_number' => $matchNumber++,
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $matchIds[] = DB::table('tournament_matches')->insertGetId([
        'pool_id' => $poolId, 'round' => 2, 'match_number' => $matchNumber++,
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $matchIds[] = DB::table('tournament_matches')->insertGetId([
        'pool_id' => $poolId, 'round' => 2, 'match_number' => $matchNumber++,
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $matchIds[] = DB::table('tournament_matches')->insertGetId([
        'pool_id' => $poolId, 'round' => 2, 'match_number' => $matchNumber++,
        'created_at' => $now, 'updated_at' => $now,
    ]);

    // ROUND 3 - 2 MATCH
    $matchIds[] = DB::table('tournament_matches')->insertGetId([
        'pool_id' => $poolId, 'round' => 3, 'match_number' => $matchNumber++,
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $matchIds[] = DB::table('tournament_matches')->insertGetId([
        'pool_id' => $poolId, 'round' => 3, 'match_number' => $matchNumber++,
        'created_at' => $now, 'updated_at' => $now,
    ]);

    // FINAL
    $matchIds[] = DB::table('tournament_matches')->insertGetId([
        'pool_id' => $poolId, 'round' => 4, 'match_number' => $matchNumber++,
        'created_at' => $now, 'updated_at' => $now,
    ]);

    // NEXT MATCH RELATION (manual mapping sesuai struktur)
    $map = [
        0 => 6, // M1 -> M7
        1 => 6, // M2 -> M7
        2 => 7, // M3 -> M8
        3 => 7, // M4 -> M8
        4 => 8, // M5 -> M9
        5 => 8, // M6 -> M9
        6 => 10, // M7 -> M11
        7 => 10, // M8 -> M11
        8 => 11, // M9 -> M12
        9 => 11, // M10 -> M12
        10 => 12, // M11 -> Final
        11 => 12, // M12 -> Final
    ];

    foreach ($map as $from => $to) {
        DB::table('tournament_matches')->where('id', $matchIds[$from])->update([
            'next_match_id' => $matchIds[$to]
        ]);
    }

    $inserted = TournamentMatch::where('pool_id', $poolId)
        ->orderBy('round')
        ->orderBy('match_number')
        ->get();

    return response()->json([
        'message' => 'Bracket 10 peserta berhasil dibuat.',
        'rounds' => $inserted,
    ]);
}








private function generateBracketForTen1($poolId, $participants)
{
    TournamentMatch::where('pool_id', $poolId)->delete();

    $pool = Pool::with('tournament')->find($poolId);
    if (!$pool) {
        return response()->json(['message' => 'Pool tidak ditemukan.'], 404);
    }

    $usedParticipantIds = TournamentMatch::whereHas('pool', function ($q) use ($pool) {
        $q->where('tournament_id', $pool->tournament_id);
    })
    ->pluck('participant_1')
    ->merge(
        TournamentMatch::whereHas('pool', function ($q) use ($pool) {
            $q->where('tournament_id', $pool->tournament_id);
        })
        ->pluck('participant_2')
    )->unique();

    $participants = $participants->reject(function ($p) use ($usedParticipantIds) {
        return $usedParticipantIds->contains($p->id);
    })->values();

    if ($participants->count() < 10) {
        return response()->json([
            'message' => 'Peserta kurang dari 10 setelah disaring dari pool lain.',
        ], 400);
    }

    $selected = $participants->slice(0, 10)->values();
    $participantIds = $selected->pluck('id')->toArray();

    TournamentParticipant::whereIn('team_member_id', $participantIds)
        ->where('tournament_id', $pool->tournament_id)
        ->update(['pool_id' => $poolId]);

    $matchNumber = 1;

    // Babak 1
    $match1Id = DB::table('tournament_matches')->insertGetId([
        'pool_id' => $poolId,
        'round' => 1,
        'match_number' => $matchNumber++,
        'participant_1' => $selected[0]->id,
        'participant_2' => null,
        'winner_id' => $selected[0]->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $match2Id = DB::table('tournament_matches')->insertGetId([
        'pool_id' => $poolId,
        'round' => 1,
        'match_number' => $matchNumber++,
        'participant_1' => $selected[1]->id,
        'participant_2' => $selected[2]->id,
        'winner_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $match3Id = DB::table('tournament_matches')->insertGetId([
        'pool_id' => $poolId,
        'round' => 1,
        'match_number' => $matchNumber++,
        'participant_1' => $selected[3]->id,
        'participant_2' => $selected[4]->id,
        'winner_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $match4Id = DB::table('tournament_matches')->insertGetId([
        'pool_id' => $poolId,
        'round' => 1,
        'match_number' => $matchNumber++,
        'participant_1' => $selected[5]->id,
        'participant_2' => $selected[6]->id,
        'winner_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $match5Id = DB::table('tournament_matches')->insertGetId([
        'pool_id' => $poolId,
        'round' => 1,
        'match_number' => $matchNumber++,
        'participant_1' => $selected[7]->id,
        'participant_2' => $selected[8]->id,
        'winner_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $match6Id = DB::table('tournament_matches')->insertGetId([
        'pool_id' => $poolId,
        'round' => 1,
        'match_number' => $matchNumber++,
        'participant_1' => $selected[9]->id,
        'participant_2' => null,
        'winner_id' => $selected[9]->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Babak 2
    $match7Id = DB::table('tournament_matches')->insertGetId([
        'pool_id' => $poolId,
        'round' => 2,
        'match_number' => $matchNumber++,
        'participant_1' => $selected[0]->id,
        'participant_2' => null,
        'winner_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $match8Id = DB::table('tournament_matches')->insertGetId([
        'pool_id' => $poolId,
        'round' => 2,
        'match_number' => $matchNumber++,
        'participant_1' => null,
        'participant_2' => $selected[9]->id,
        'winner_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Final
    $match9Id = DB::table('tournament_matches')->insertGetId([
        'pool_id' => $poolId,
        'round' => 3,
        'match_number' => $matchNumber++,
        'participant_1' => null,
        'participant_2' => null,
        'winner_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Update next_match_id
    DB::table('tournament_matches')->where('id', $match1Id)->update(['next_match_id' => $match7Id]);
    DB::table('tournament_matches')->where('id', $match2Id)->update(['next_match_id' => $match7Id]);
    DB::table('tournament_matches')->where('id', $match3Id)->update(['next_match_id' => $match8Id]);
    DB::table('tournament_matches')->where('id', $match4Id)->update(['next_match_id' => $match8Id]);
    DB::table('tournament_matches')->where('id', $match5Id)->update(['next_match_id' => $match8Id]);
    DB::table('tournament_matches')->where('id', $match6Id)->update(['next_match_id' => $match8Id]);
    DB::table('tournament_matches')->where('id', $match7Id)->update(['next_match_id' => $match9Id]);
    DB::table('tournament_matches')->where('id', $match8Id)->update(['next_match_id' => $match9Id]);

    $inserted = TournamentMatch::where('pool_id', $poolId)
        ->orderBy('round')
        ->orderBy('match_number')
        ->get();

    return response()->json([
        'message' => 'Bracket untuk 10 peserta berhasil dibuat.',
        'rounds' => $inserted,
    ]);
    
}





    
private function generateFullPrestasiBracket__yang_dipake($poolId, $participants)
{
    TournamentMatch::where('pool_id', $poolId)->delete();

    $pool = Pool::with('tournament')->find($poolId);
    if (!$pool) {
        return response()->json(['message' => 'Pool tidak ditemukan.'], 404);
    }

    $tournamentId = $pool->tournament_id;
    $desiredClassId = $pool->category_class_id;
    $desiredMatchCategoryId = $pool->match_category_id;

    if (!$desiredClassId || !$desiredMatchCategoryId) {
        return response()->json(['message' => 'Pool tidak memiliki kelas atau kategori pertandingan.'], 400);
    }

    // Ambil peserta eligible
    $eligibleParticipants = DB::table('tournament_participants')
        ->join('team_members', 'tournament_participants.team_member_id', '=', 'team_members.id')
        ->where('tournament_participants.tournament_id', $tournamentId)
        ->where('team_members.category_class_id', $desiredClassId)
        ->where('team_members.match_category_id', $desiredMatchCategoryId)
        ->select('tournament_participants.id as tp_id', 'team_members.id as id', 'team_members.name')
        ->get();

    if ($eligibleParticipants->count() == 6) {
        return $this->generateBracketForSix($poolId, $eligibleParticipants);
    }

    // lanjut pakai skema general seperti sebelumnya
    // Kosongkan pool_id peserta dari pool ini dan yang cocok class & kategori
    DB::table('tournament_participants')
        ->where('pool_id', $poolId)
        ->update(['pool_id' => null]);

    DB::table('tournament_participants')
        ->join('team_members', 'tournament_participants.team_member_id', '=', 'team_members.id')
        ->where('tournament_participants.tournament_id', $tournamentId)
        ->where('team_members.category_class_id', $desiredClassId)
        ->where('team_members.match_category_id', $desiredMatchCategoryId)
        ->update(['tournament_participants.pool_id' => null]);

    $shuffled = $eligibleParticipants->shuffle()->values();
    $participantIdsToUpdate = $shuffled->pluck('tp_id');

    \Log::info("\u2705 Peserta masuk pool {$poolId}", [
        'total' => $participantIdsToUpdate->count(),
        'nama' => $shuffled->pluck('name'),
    ]);

    DB::table('tournament_participants')
        ->whereIn('id', $participantIdsToUpdate)
        ->update(['pool_id' => $poolId]);

    $participantIds = $shuffled->pluck('id')->shuffle()->values();
    $total = $participantIds->count();
    $bracketSize = pow(2, ceil(log($total, 2)));
    $rounds = ceil(log($bracketSize, 2));
    $matchNumber = 1;
    $matchRefs = [];

    for ($round = 1; $round <= $rounds; $round++) {
        $numMatches = $bracketSize / pow(2, $round);
        for ($i = 0; $i < $numMatches; $i++) {
            $matchRefs[$matchNumber] = [
                'pool_id' => $poolId,
                'round' => $round,
                'match_number' => $matchNumber,
                'participant_1' => null,
                'participant_2' => null,
                'winner_id' => null,
                'next_match_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $matchNumber++;
        }
    }

    $i = 0;
    foreach ($matchRefs as $number => &$match) {
        if ($match['round'] !== 1) continue;

        $match['participant_1'] = $participantIds[$i++] ?? null;
        $match['participant_2'] = $participantIds[$i++] ?? null;

        if ($match['participant_1'] && !$match['participant_2']) {
            $match['winner_id'] = $match['participant_1'];
        } elseif (!$match['participant_1'] && $match['participant_2']) {
            $match['winner_id'] = $match['participant_2'];
        }
    }
    unset($match);

    foreach ($matchRefs as $number => $match) {
        if ($match['round'] === 1 && is_null($match['participant_1']) && is_null($match['participant_2'])) {
            unset($matchRefs[$number]);
        }
    }

    $roundGrouped = collect($matchRefs)->groupBy('round');
    foreach ($roundGrouped as $r => $group) {
        if (isset($roundGrouped[$r + 1])) {
            $nextMatches = $roundGrouped[$r + 1]->values();
            foreach ($group->values() as $i => $match) {
                $matchRefs[$match['match_number']]['next_match_number'] = $nextMatches[floor($i / 2)]['match_number'] ?? null;
            }
        }
    }

    $matchesToInsert = array_map(function ($match) {
        unset($match['next_match_id']);
        return Arr::except($match, ['next_match_number']);
    }, array_values($matchRefs));

    DB::table('tournament_matches')->insert($matchesToInsert);

    $matchMap = TournamentMatch::where('pool_id', $poolId)->get()->keyBy('match_number');

    foreach ($matchRefs as $matchData) {
        if (isset($matchData['next_match_number'])) {
            $match = $matchMap[$matchData['match_number']] ?? null;
            $nextMatch = $matchMap[$matchData['next_match_number']] ?? null;

            if ($match && $nextMatch) {
                $match->next_match_id = $nextMatch->id;
                $match->save();

                if ($match->winner_id && is_null($nextMatch->participant_1)) {
                    $nextMatch->participant_1 = $match->winner_id;
                    $nextMatch->save();
                } elseif ($match->winner_id && is_null($nextMatch->participant_2)) {
                    $nextMatch->participant_2 = $match->winner_id;
                    $nextMatch->save();
                }
            }
        }
    }

    return response()->json([
        'message' => 'Bracket berhasil dibuat dan peserta sudah di-assign ke pool.',
        'total_participants' => $total,
        'bracket_size' => $bracketSize,
        'total_matches' => count($matchRefs),
        'rounds_generated' => $rounds,
    ]);
}



  private function generateFullPrestasiBracket_keep($poolId, $participants)
{
    TournamentMatch::where('pool_id', $poolId)->delete();

    $pool = Pool::with('tournament')->find($poolId);
    if (!$pool) {
        return response()->json(['message' => 'Pool tidak ditemukan.'], 404);
    }

    $tournamentId = $pool->tournament_id;
    $desiredClassId = $pool->category_class_id;
    $desiredMatchCategoryId = $pool->match_category_id;

    if (!$desiredClassId || !$desiredMatchCategoryId) {
        return response()->json(['message' => 'Pool tidak memiliki kelas atau kategori pertandingan.'], 400);
    }

    // ✅ Kosongkan pool_id semua peserta yang sudah pernah masuk pool ini
    DB::table('tournament_participants')
        ->where('pool_id', $poolId)
        ->update(['pool_id' => null]);

    // ✅ Kosongkan pool_id peserta yang sesuai class dan match agar tidak nyangkut
    DB::table('tournament_participants')
        ->join('team_members', 'tournament_participants.team_member_id', '=', 'team_members.id')
        ->where('tournament_participants.tournament_id', $tournamentId)
        ->where('team_members.category_class_id', $desiredClassId)
        ->where('team_members.match_category_id', $desiredMatchCategoryId)
        ->update(['tournament_participants.pool_id' => null]);

    // 🔍 Ambil peserta yang cocok class & match
    $eligibleParticipants = DB::table('tournament_participants')
        ->join('team_members', 'tournament_participants.team_member_id', '=', 'team_members.id')
        ->where('tournament_participants.tournament_id', $tournamentId)
        ->whereNull('tournament_participants.pool_id')
        ->where('team_members.category_class_id', $desiredClassId)
        ->where('team_members.match_category_id', $desiredMatchCategoryId)
        ->select(
            'tournament_participants.id as tp_id',
            'team_members.id as team_member_id',
            'team_members.name as name'
        )
        ->get();

    if ($eligibleParticipants->isEmpty()) {
        return response()->json(['message' => 'Tidak ada peserta yang cocok.'], 404);
    }

    $shuffled = $eligibleParticipants->shuffle()->values();
    $participantIdsToUpdate = $shuffled->pluck('tp_id');

    // 📝 Log siapa saja yang dimasukkan ke pool
    \Log::info("✅ Peserta masuk pool {$poolId}", [
        'total' => $participantIdsToUpdate->count(),
        'nama' => $shuffled->pluck('name'),
    ]);

    DB::table('tournament_participants')
        ->whereIn('id', $participantIdsToUpdate)
        ->update(['pool_id' => $poolId]);

    // 🔁 Ambil ulang peserta berdasarkan pool
    $participantIds = DB::table('tournament_participants')
        ->join('team_members', 'tournament_participants.team_member_id', '=', 'team_members.id')
        ->where('tournament_participants.pool_id', $poolId)
        ->select('team_members.id')
        ->pluck('team_members.id')
        ->shuffle()
        ->values();

    $total = $participantIds->count();
    $bracketSize = pow(2, ceil(log($total, 2)));
    $rounds = ceil(log($bracketSize, 2));
    $matchNumber = 1;
    $matchRefs = [];

    for ($round = 1; $round <= $rounds; $round++) {
        $numMatches = $bracketSize / pow(2, $round);
        for ($i = 0; $i < $numMatches; $i++) {
            $matchRefs[$matchNumber] = [
                'pool_id' => $poolId,
                'round' => $round,
                'match_number' => $matchNumber,
                'participant_1' => null,
                'participant_2' => null,
                'winner_id' => null,
                'next_match_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $matchNumber++;
        }
    }

    // 🧩 Isi round 1
    $i = 0;
    foreach ($matchRefs as $number => &$match) {
        if ($match['round'] !== 1) continue;

        $match['participant_1'] = $participantIds[$i++] ?? null;
        $match['participant_2'] = $participantIds[$i++] ?? null;

        if ($match['participant_1'] && !$match['participant_2']) {
            $match['winner_id'] = $match['participant_1'];
        } elseif (!$match['participant_1'] && $match['participant_2']) {
            $match['winner_id'] = $match['participant_2'];
        }
    }
    unset($match);

    // 🔗 Link antar match
    $roundGrouped = collect($matchRefs)->groupBy('round');
    foreach ($roundGrouped as $r => $group) {
        if (isset($roundGrouped[$r + 1])) {
            $nextMatches = $roundGrouped[$r + 1]->values();
            foreach ($group->values() as $i => $match) {
                $matchRefs[$match['match_number']]['next_match_number'] = $nextMatches[floor($i / 2)]['match_number'] ?? null;
            }
        }
    }

    // 🧹 Hapus match kosong
    foreach ($matchRefs as $number => $match) {
        if ($match['round'] === 1 && is_null($match['participant_1']) && is_null($match['participant_2'])) {
            unset($matchRefs[$number]);
        }
    }

    // 💾 Simpan match
    $matchesToInsert = array_map(function ($match) {
        unset($match['next_match_id']);
        return Arr::except($match, ['next_match_number']);
    }, array_values($matchRefs));

    DB::table('tournament_matches')->insert($matchesToInsert);

    // 🔄 Update next match ID
    $matchMap = TournamentMatch::where('pool_id', $poolId)->get()->keyBy('match_number');

    foreach ($matchRefs as $matchData) {
        if (isset($matchData['next_match_number'])) {
            $match = $matchMap[$matchData['match_number']] ?? null;
            $nextMatch = $matchMap[$matchData['next_match_number']] ?? null;

            if ($match && $nextMatch) {
                $match->next_match_id = $nextMatch->id;
                $match->save();

                if ($match->winner_id && is_null($nextMatch->participant_1)) {
                    $nextMatch->participant_1 = $match->winner_id;
                    $nextMatch->save();
                } elseif ($match->winner_id && is_null($nextMatch->participant_2)) {
                    $nextMatch->participant_2 = $match->winner_id;
                    $nextMatch->save();
                }
            }
        }
    }

    return response()->json([
        'message' => 'Bracket berhasil dibuat dan peserta sudah di-assign ke pool.',
        'total_participants' => $total,
        'bracket_size' => $bracketSize,
        'total_matches' => count($matchRefs),
        'rounds_generated' => $rounds,
    ]);
}





    
  private function generateFullPrestasiBracket_jangan($poolId, $participants)
{
    TournamentMatch::where('pool_id', $poolId)->delete();

    $pool = Pool::with('tournament')->find($poolId);
    if (!$pool) {
        return response()->json(['message' => 'Pool tidak ditemukan.'], 404);
    }

    $tournamentId = $pool->tournament_id;
    $desiredClassId = $pool->category_class_id;

    if (!$desiredClassId) {
        return response()->json(['message' => 'Pool tidak memiliki kelas.'], 400);
    }

    \Log::info("🎯 Cari peserta tournament_id = $tournamentId, class_id = $desiredClassId");

    // Ambil semua peserta dari turnamen ini yang sesuai class pool
    $participantIds = DB::table('tournament_participants')
        ->join('team_members', 'tournament_participants.team_member_id', '=', 'team_members.id')
        ->where('tournament_participants.tournament_id', $tournamentId)
        ->whereNull('tournament_participants.pool_id') // belum di-assign ke pool
        ->where('team_members.category_class_id', $desiredClassId)
        ->pluck('team_members.id');

    if ($participantIds->isEmpty()) {
        return response()->json(['message' => 'Tidak ada peserta yang cocok.'], 404);
    }

    // Acak dan simpan ulang pool_id-nya
    $shuffled = $participantIds->shuffle()->values();

    DB::table('tournament_participants')
        ->join('team_members', 'tournament_participants.team_member_id', '=', 'team_members.id')
        ->where('tournament_participants.tournament_id', $tournamentId)
        ->whereNull('tournament_participants.pool_id')
        ->whereIn('team_members.id', $shuffled)
        ->update(['tournament_participants.pool_id' => $poolId]);

    // Generate ulang peserta dengan pool_id yang udah diassign barusan
    $participants = DB::table('tournament_participants')
        ->join('team_members', 'tournament_participants.team_member_id', '=', 'team_members.id')
        ->where('tournament_participants.pool_id', $poolId)
        ->select('team_members.id')
        ->get();

    // Sisa generate bracket seperti biasa
    $participantIds = collect($participants)->pluck('id')->shuffle()->values();
    $total = $participantIds->count();

    $bracketSize = pow(2, ceil(log($total, 2)));
    $rounds = ceil(log($bracketSize, 2));
    $matchNumber = 1;
    $matchRefs = [];

    for ($round = 1; $round <= $rounds; $round++) {
        $numMatches = $bracketSize / pow(2, $round);
        for ($i = 0; $i < $numMatches; $i++) {
            $matchRefs[$matchNumber] = [
                'pool_id' => $poolId,
                'round' => $round,
                'match_number' => $matchNumber,
                'participant_1' => null,
                'participant_2' => null,
                'winner_id' => null,
                'next_match_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $matchNumber++;
        }
    }

    // Isi peserta
    $i = 0;
    foreach ($matchRefs as $number => &$match) {
        if ($match['round'] !== 1) continue;

        $match['participant_1'] = $participantIds[$i++] ?? null;
        $match['participant_2'] = $participantIds[$i++] ?? null;

        if ($match['participant_1'] && !$match['participant_2']) {
            $match['winner_id'] = $match['participant_1'];
        } elseif (!$match['participant_1'] && $match['participant_2']) {
            $match['winner_id'] = $match['participant_2'];
        }
    }
    unset($match);

    // Next match linking
    $roundGrouped = collect($matchRefs)->groupBy('round');
    foreach ($roundGrouped as $r => $group) {
        if (isset($roundGrouped[$r + 1])) {
            $nextMatches = $roundGrouped[$r + 1]->values();
            foreach ($group->values() as $i => $match) {
                $matchRefs[$match['match_number']]['next_match_number'] = $nextMatches[floor($i / 2)]['match_number'] ?? null;
            }
        }
    }

    // Hapus match kosong
    foreach ($matchRefs as $number => $match) {
        if ($match['round'] === 1 && is_null($match['participant_1']) && is_null($match['participant_2'])) {
            unset($matchRefs[$number]);
        }
    }

    // Insert match awal
    $matchesToInsert = array_map(function ($match) {
        unset($match['next_match_id']);
        return Arr::except($match, ['next_match_number']);
    }, array_values($matchRefs));

    DB::table('tournament_matches')->insert($matchesToInsert);

    // Update next_match_id
    $matchMap = TournamentMatch::where('pool_id', $poolId)->get()->keyBy('match_number');

    foreach ($matchRefs as $matchData) {
        if (isset($matchData['next_match_number'])) {
            $match = $matchMap[$matchData['match_number']] ?? null;
            $nextMatch = $matchMap[$matchData['next_match_number']] ?? null;

            if ($match && $nextMatch) {
                $match->next_match_id = $nextMatch->id;
                $match->save();

                if ($match->winner_id && is_null($nextMatch->participant_1)) {
                    $nextMatch->participant_1 = $match->winner_id;
                    $nextMatch->save();
                } elseif ($match->winner_id && is_null($nextMatch->participant_2)) {
                    $nextMatch->participant_2 = $match->winner_id;
                    $nextMatch->save();
                }
            }
        }
    }

    return response()->json([
        'message' => 'Bracket berhasil dibuat dan peserta sudah di-assign ke pool.',
        'total_participants' => $total,
        'bracket_size' => $bracketSize,
        'total_matches' => count($matchRefs),
        'rounds_generated' => $rounds,
    ]);
}






    private function generateSingleElimination($tournamentId, $poolId, $participants, $matchChart)
    {
        TournamentMatch::where('pool_id', $poolId)->delete();

        $pool = Pool::with('tournament')->find($poolId);
        if (!$pool) {
            return response()->json(['message' => 'Pool tidak ditemukan.'], 404);
        }

        $usedParticipantIds = TournamentMatch::whereHas('pool', function ($q) use ($tournamentId) {
            $q->where('tournament_id', $tournamentId);
        })
        ->pluck('participant_1')
        ->merge(
            TournamentMatch::whereHas('pool', function ($q) use ($tournamentId) {
                $q->where('tournament_id', $tournamentId);
            })
            ->pluck('participant_2')
        )->unique();

        $participants = $participants->reject(function ($p) use ($usedParticipantIds) {
            return $usedParticipantIds->contains($p->id);
        })->values();

        if ($participants->isEmpty()) {
            return response()->json([
                'message' => 'Semua peserta sudah masuk match di pool lain.',
            ], 400);
        }

        // Tentukan jumlah peserta maksimal yang dipakai di babak 1
        $maxParticipantCount = (int) $matchChart;
        $selectedParticipants = $participants->slice(0, $maxParticipantCount)->values();
        $participantIds = $selectedParticipants->pluck('id')->toArray();

        // ✅ Update pool_id HANYA untuk peserta yang dipakai
        TournamentParticipant::whereIn('team_member_id', $participantIds)
            ->where('tournament_id', $tournamentId)
            ->update(['pool_id' => $poolId]);

        $totalRounds = (int) log($matchChart, 2);
        $matchNumber = 1;
        $matches = collect();

        for ($round = 1; $round <= $totalRounds; $round++) {
            $matchCount = $matchChart / pow(2, $round);
            for ($i = 0; $i < $matchCount; $i++) {
                $matches->push([
                    'pool_id' => $poolId,
                    'round' => $round,
                    'match_number' => $matchNumber++,
                    'participant_1' => null,
                    'participant_2' => null,
                    'winner_id' => null,
                    'next_match_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('tournament_matches')->insert($matches->toArray());

        $allMatches = TournamentMatch::where('pool_id', $poolId)
            ->orderBy('round')
            ->orderBy('match_number')
            ->get();

        $byRound = $allMatches->groupBy('round');
        foreach ($byRound as $round => $roundMatches) {
            if (isset($byRound[$round + 1])) {
                $nextMatches = $byRound[$round + 1];
                foreach ($roundMatches as $i => $match) {
                    $parentIndex = floor($i / 2);
                    $match->next_match_id = $nextMatches[$parentIndex]->id ?? null;
                    $match->save();
                }
            }
        }

        // Assign peserta ke babak pertama
        $firstRoundMatches = $byRound[1];
        $index = 0;
        foreach ($firstRoundMatches as $match) {
            $match->participant_1 = $participantIds[$index++] ?? null;
            $match->participant_2 = $participantIds[$index++] ?? null;

            if ($match->participant_1 && !$match->participant_2) {
                $match->winner_id = $match->participant_1;
            }

            $match->save();
        }

        return response()->json([
            'message' => 'Bracket eliminasi tunggal berhasil dibuat.',
            'rounds' => $allMatches,
        ]);
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
        $pool = Pool::findOrFail($poolId);
        $matchChart = (int) $pool->match_chart;

        $matches = TournamentMatch::where('pool_id', $poolId)
            ->with(['participantOne.contingent', 'participantTwo.contingent', 'winner'])
            ->orderBy('round')
            ->orderBy('match_number')
            ->get();

        $groupedRounds = [];
        $allGamesEmpty = true;

        foreach ($matches as $match) {
            $round = $match->round;

            if (!isset($groupedRounds[$round])) {
                $groupedRounds[$round] = ['games' => []];
            }

            $player1 = $match->participantOne;
            $player2 = $match->participantTwo;
            $winner = $match->winner;

            $game = [
                'player1' => $player1 ? [
                    'id' => (string) $player1->id,
                    'name' => $player1->name,
                    'contingent' => $player1->contingent->name ?? '-', // ✅ nama kontingen
                    'winner' => $winner && $winner->id === $player1->id
                ] : [
                    'id' => null,
                    'name' => $round === 1 ? 'BYE' : 'TBD',
                    'contingent' => '-',
                    'winner' => false
                ],
                'player2' => $player2 ? [
                    'id' => (string) $player2->id,
                    'name' => $player2->name,
                    'contingent' => $player2->contingent->name ?? '-', // ✅ nama kontingen
                    'winner' => $winner && $winner->id === $player2->id
                ] : [
                    'id' => null,
                    'name' => $round === 1 ? 'BYE' : 'TBD',
                    'contingent' => '-',
                    'winner' => false
                ]
            ];

            if ($player1 || $player2) {
                $allGamesEmpty = false;
            }

            $groupedRounds[$round]['games'][] = $game;
        }

        ksort($groupedRounds);

        $rounds = array_values(array_map(function ($round) {
            return ['games' => $round['games']];
        }, $groupedRounds));

        return response()->json([
            'rounds' => $rounds,
            'match_chart' => $matchChart,
            'status' => $allGamesEmpty ? 'pending' : 'ongoing',
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
            'pool:id,name,tournament_id,category_class_id,match_category_id',
            'pool.categoryClass:id,name,age_category_id,gender',
            'pool.categoryClass.ageCategory:id,name', // ✅ ini buat ambil nama usia
            'pool.matchCategory:id,name',
        ])                
        ->whereHas('pool', function ($q) use ($tournamentId) {
            $q->where('tournament_id', $tournamentId);
        });

        // ✅ Exclude scheduled match kalau tidak ada flag include_scheduled
        if (!$request->boolean('include_scheduled')) {
            $query->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('match_schedule_details')
                    ->whereColumn('match_schedule_details.tournament_match_id', 'tournament_matches.id');
            });
        }

        // Filter lainnya tetap
        if ($request->has('match_category_id')) {
            $query->whereHas('pool', function ($q) use ($request) {
                $q->where('match_category_id', $request->match_category_id);
            });
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

        // Group dan return data tetap
        $matches = $query
            ->orderBy('pool_id')
            ->orderBy('round')
            ->orderBy('match_number')
            ->get();

        $groupedMatches = $matches->groupBy('pool_id');

        $data = $groupedMatches->map(function ($matches, $poolId) {
            $firstMatch = $matches->first();
            $pool = $firstMatch->pool;

            $roundGroups = $matches->groupBy('round');
            $totalRounds = $roundGroups->count();
            $roundLabels = $this->getRoundLabels($totalRounds);

            $rounds = $roundGroups->map(function ($matchesInRound, $round) use ($roundLabels) {
                return [
                    'round' => (int) $round,
                    'round_label' => $roundLabels[$round] ?? "Babak {$round}",
                    'matches' => $matchesInRound->values()
                ];
            })->values();

            
            Log::info([
                'class_id' => $pool->category_class_id,
                'age_id_from_pool' => $pool->categoryClass->age_category_id ?? null,
                'age_name_from_rel' => $pool->categoryClass->ageCategory->name ?? null,
            ]);

            
            return [
                'pool_id'    => $poolId,
                'pool_name'  => $pool->name,
                'match_category_id' => $pool->matchCategory->id ?? null,
                'class_name' => $pool->categoryClass->name ?? '-',
                'age_category_id' => $pool->categoryClass->age_category_id ?? null,
                'age_category_name' => $pool->categoryClass->ageCategory->name ?? '-',
                'gender' => $pool->categoryClass->gender ?? null,
                'rounds'     => $rounds
            ];
            
            
            
        })->values();

        return response()->json([
            'message' => 'List pertandingan berhasil diambil',
            'data' => $data
        ]);
    }


    
    private function getRoundLabels($totalRounds)
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


    public function getAvailableRounds(Request $request, $tournamentId)
    {
       
    
        $roundLevels = \App\Models\TournamentMatch::query()
        ->select('tournament_matches.round')
        ->join('pools', 'pools.id', '=', 'tournament_matches.pool_id')
        ->where('pools.tournament_id', $tournamentId)
        ->distinct()
        ->pluck('tournament_matches.round')
        ->toArray();
    
    
        if (empty($roundLevels)) {
            return response()->json([]);
        }
    
        $totalRounds = max($roundLevels);
        $allLabels = $this->getRoundLabels($totalRounds);
    
        $filteredLabels = [];
        foreach ($roundLevels as $level) {
            if (isset($allLabels[$level])) {
                $filteredLabels[$level] = $allLabels[$level];
            }
        }
    
        return response()->json([
            'rounds' => $filteredLabels
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
