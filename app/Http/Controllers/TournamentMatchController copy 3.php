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
    private function getRoundLabel($round, $maxRound)
    {
        $labels = [
            0 => 'Final',
            1 => 'Semifinal',
            2 => '1/4 Final',
            3 => '1/8 Final',
            4 => '1/16 Final',
            5 => '1/32 Final',
            6 => '1/64 Final',
        ];

        $diff = $maxRound - $round;
        return $labels[$diff] ?? 'Penyisihan';
    }
    

    public function generateBracket($poolId)
    {
        // Ambil pool dan data penting
        $pool = Pool::with('categoryClass')->find($poolId);
        if (!$pool) {
            return response()->json(['message' => 'Pool tidak ditemukan.'], 404);
        }

        $tournamentId = $pool->tournament_id;
        $matchChart = (int) $pool->match_chart;

        $matchCategoryId = $pool->match_category_id;
        $categoryClassId = $pool->category_class_id;
        $ageCategoryId = $pool->age_category_id;

        // Ambil peserta yang belum masuk ke match
        $existingMatches = TournamentMatch::where('pool_id', $poolId)->pluck('participant_1')
            ->merge(
                TournamentMatch::where('pool_id', $poolId)->pluck('participant_2')
            )->unique();

        // 🔍 Ambil peserta berdasarkan match_category_id, class, dan usia
        $participants = DB::table('tournament_participants')
            ->join('team_members', 'tournament_participants.team_member_id', '=', 'team_members.id')
            ->where('tournament_participants.tournament_id', $tournamentId)
            ->whereNotIn('team_members.id', $existingMatches)
            ->when($matchCategoryId, fn($q) => $q->where('team_members.match_category_id', $matchCategoryId))
            ->when($categoryClassId, fn($q) => $q->where('team_members.category_class_id', $categoryClassId))
            ->when($ageCategoryId, fn($q) => $q->where('team_members.age_category_id', $ageCategoryId))
            ->select('team_members.id', 'team_members.name', 'team_members.contingent_id')
            ->get()
            ->shuffle();

        if ($participants->isEmpty()) {
            return response()->json(['message' => 'Semua peserta sudah memiliki match atau tidak ada peserta valid.'], 400);
        }

        // Cek jenis bagan
        if ($matchChart == 2) {
            return $this->generateSingleRoundBracket($poolId, $participants);
        }

        if ($matchChart == 6) {
            return $this->generateBracketForSix($poolId, $participants);
        }

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

        // Coba ambil peserta yang sudah masuk pool ini
        $participants = collect(
            DB::table('tournament_participants')
                ->join('team_members', 'tournament_participants.team_member_id', '=', 'team_members.id')
                ->where('tournament_participants.pool_id', $poolId)
                ->select('team_members.id', 'team_members.name')
                ->get()
        );

        // Kalau belum ada isinya, ambil peserta dari turnamen yg belum punya pool_id
        if ($participants->isEmpty()) {
            $participants = collect(
                DB::table('tournament_participants')
                    ->join('team_members', 'tournament_participants.team_member_id', '=', 'team_members.id')
                    ->where('tournament_participants.tournament_id', $tournamentId)
                    ->whereNull('tournament_participants.pool_id')
                    ->select('team_members.id', 'team_members.name')
                    ->get()
            );
        }

        // Shuffle ulang
        $participants = $participants->shuffle()->values();
        $participantCount = $participants->count();

        // Validasi jumlah peserta
        if (!in_array($matchChart, ['full_prestasi', 0, 6]) && $participantCount < $matchChart) {
            return response()->json([
                'message' => 'Peserta tidak mencukupi untuk membuat bagan ini.',
                'found' => $participantCount,
                'needed' => $matchChart
            ], 400);
        }

        // Generate berdasarkan match chart
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




    public function regenerateBracket_asli($poolId)
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


    

    private function generateSingleRoundBracket($poolId)
    {
        TournamentMatch::where('pool_id', $poolId)->delete();

        $pool = Pool::find($poolId);
        if (!$pool) {
            return response()->json(['message' => 'Pool tidak ditemukan.'], 404);
        }

        $matchCategoryId = $pool->match_category_id;
        if (!$matchCategoryId) {
            return response()->json(['message' => 'Match Category ID tidak ditemukan di pool.'], 400);
        }

        \Log::debug('🎯 Pool Info', [
            'pool_id' => $poolId,
            'tournament_id' => $pool->tournament_id,
            'age_category_id' => $pool->age_category_id,
            'match_category_id' => $matchCategoryId,
            'category_class_id' => $pool->category_class_id,
        ]);

        $participants = DB::table('team_members')
            ->join('tournament_participants', 'team_members.id', '=', 'tournament_participants.team_member_id')
            ->where('tournament_participants.tournament_id', $pool->tournament_id)
            ->where('team_members.age_category_id', $pool->age_category_id)
            ->where('team_members.match_category_id', $matchCategoryId)
            ->where('team_members.category_class_id', $pool->category_class_id)
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

        \Log::debug('🧪 Peserta ditemukan:', [
            'count' => $participants->count(),
            'ids' => $participants->pluck('id')
        ]);

        if ($participants->isEmpty()) {
            return response()->json(['message' => 'Tidak ada peserta valid untuk pool ini.'], 400);
        }

        $matches = collect();
        $matchNumber = 1;

        $grouped = $participants->groupBy(function ($p) {
            return ($p->category_class_id ?? 'null') . '-' . ($p->gender ?? 'null');
        });

        foreach ($grouped as $group) {
            $queue = $group->shuffle()->values();

            while ($queue->count() > 0) {
                $p1 = $queue->shift();

                $opponentIndex = $queue->search(fn($p2) =>
                    $p2->contingent_id !== $p1->contingent_id &&
                    $p2->id !== $p1->id
                );

                $p2 = $opponentIndex !== false ? $queue->pull($opponentIndex) : null;

                $matches->push([
                    'pool_id' => $poolId,
                    'round' => 1,
                    'round_label' => $this->getRoundLabel(1, 1),
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
            'message' => 'Bracket berhasil dibuat.',
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

        $usedParticipantIds = TournamentMatch::whereHas('pool', fn($q) =>
            $q->where('tournament_id', $pool->tournament_id)
        )->pluck('participant_1')
        ->merge(
            TournamentMatch::whereHas('pool', fn($q) =>
                $q->where('tournament_id', $pool->tournament_id)
            )->pluck('participant_2')
        )->unique();

        $participants = $participants->reject(fn($p) => $usedParticipantIds->contains($p->id))->values();
        $selected = $participants->slice(0, 6)->values();
        $participantIds = $selected->pluck('id')->toArray();

        TournamentParticipant::whereIn('team_member_id', $participantIds)
            ->where('tournament_id', $pool->tournament_id)
            ->update(['pool_id' => $poolId]);

        if ($selected->count() === 5) { 
            return $this->generateBracketForFive($poolId, $selected);
        }

        if ($selected->count() === 6) {
            $rounds = $this->generateDefaultSix($poolId, $selected);
            return response()->json([
                'message' => 'Bracket untuk 6 peserta berhasil dibuat.',
                'rounds' => $rounds,
            ]);
        }

        // Untuk 1-5 peserta gunakan sistem knockout standar
        $matchNumber = 1;
        $matchMap = [];
        $queue = $selected->pluck('id')->toArray();

        // Hitung jumlah slot ideal (power of 2)
        $slot = pow(2, ceil(log(max(count($queue), 2), 2)));
        $maxRound = ceil(log($slot, 2));
        $byeCount = $slot - count($queue);

        // Tambahkan slot BYE
        for ($i = 0; $i < $byeCount; $i++) {
            $queue[] = null;
        }

        // Pasangkan peserta ke round 1
        $round1 = [];
        for ($i = 0; $i < count($queue); $i += 2) {
            $p1 = $queue[$i] ?? null;
            $p2 = $queue[$i + 1] ?? null;

            $winner = ($p1 && !$p2) ? $p1 : (($p2 && !$p1) ? $p2 : null);
            $matchId = DB::table('tournament_matches')->insertGetId([
                'pool_id' => $poolId,
                'round' => 1,
                'round_label' => $this->getRoundLabel(1, $maxRound),
                'match_number' => $matchNumber++,
                'participant_1' => $p1,
                'participant_2' => $p2,
                'winner_id' => $winner,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $matchMap[] = [
                'id' => $matchId,
                'winner' => $winner,
            ];
        }

        // Round 2 dan seterusnya
        while (count($matchMap) > 1) {
            $nextRound = [];
            $currentRound = ceil(log($slot, 2)) - ceil(log(count($matchMap), 2)) + 1;
            foreach (array_chunk($matchMap, 2) as $pair) {
                $m1 = $pair[0];
                $m2 = $pair[1] ?? ['id' => null, 'winner' => null];

                $matchId = DB::table('tournament_matches')->insertGetId([
                    'pool_id' => $poolId,
                    'round' => $currentRound,
                    'round_label' => $this->getRoundLabel($currentRound, $maxRound),
                    'match_number' => $matchNumber++,
                    'participant_1' => $m1['winner'],
                    'participant_2' => $m2['winner'],
                    'winner_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($m1['id']) {
                    DB::table('tournament_matches')->where('id', $m1['id'])->update(['next_match_id' => $matchId]);
                }
                if ($m2['id']) {
                    DB::table('tournament_matches')->where('id', $m2['id'])->update(['next_match_id' => $matchId]);
                }

                $nextRound[] = [
                    'id' => $matchId,
                    'winner' => null,
                ];
            }

            $matchMap = $nextRound;
        }

        $inserted = TournamentMatch::where('pool_id', $poolId)
            ->orderBy('round')
            ->orderBy('match_number')
            ->get();

        return response()->json([
            'message' => 'Bracket untuk ' . $selected->count() . ' peserta berhasil dibuat.',
            'rounds' => $inserted,
        ]);
    }

    






   
   private function generateBracketForSix_dipak($poolId, $participants)
{
    TournamentMatch::where('pool_id', $poolId)->delete();

    $pool = Pool::with('tournament')->find($poolId);
    if (!$pool) {
        return response()->json(['message' => 'Pool tidak ditemukan.'], 404);
    }

    $participantIds = $participants->pluck('id')->toArray();
    shuffle($participantIds);

    TournamentParticipant::whereIn('team_member_id', $participantIds)
        ->where('tournament_id', $pool->tournament_id)
        ->update(['pool_id' => $poolId]);

    $count = count($participantIds);

    // Jika peserta kurang dari 6, tetap gunakan sistem gugur dasar
    if ($count < 6) {
        $rounds = (int) ceil(log($count, 2));
        $matchNumber = 1;
        $currentParticipants = $participantIds;
        $currentMatches = [];
        $previousMatches = [];

        for ($round = 1; $round <= $rounds; $round++) {
            $roundLabel = $this->getRoundLabel($round, $rounds);
            $nextMatches = [];

            while (count($currentParticipants) > 1) {
                $p1 = array_shift($currentParticipants);
                $p2 = array_shift($currentParticipants);
                $winner = null;

                $matchId = DB::table('tournament_matches')->insertGetId([
                    'pool_id' => $poolId,
                    'round' => $round,
                    'round_label' => $roundLabel,
                    'match_number' => $matchNumber,
                    'participant_1' => $p1,
                    'participant_2' => $p2,
                    'winner_id' => $winner,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $nextMatches[] = [
                    'id' => $matchId,
                    'match_number' => $matchNumber,
                ];

                $matchNumber++;
            }

            if (count($currentParticipants) === 1) {
                $p1 = array_shift($currentParticipants);
                $matchId = DB::table('tournament_matches')->insertGetId([
                    'pool_id' => $poolId,
                    'round' => $round,
                    'round_label' => $roundLabel,
                    'match_number' => $matchNumber,
                    'participant_1' => $p1,
                    'participant_2' => null,
                    'winner_id' => $p1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $nextMatches[] = [
                    'id' => $matchId,
                    'match_number' => $matchNumber,
                ];

                $matchNumber++;
            }

            // Set next_match_id untuk previous matches
            foreach (array_chunk($previousMatches, 2) as $index => $pair) {
                $nextMatch = $nextMatches[$index] ?? null;
                if ($nextMatch) {
                    foreach ($pair as $match) {
                        DB::table('tournament_matches')->where('id', $match['id'])->update([
                            'next_match_id' => $nextMatch['id'],
                        ]);
                    }
                }
            }

            $previousMatches = $nextMatches;
            $currentParticipants = array_fill(0, count($nextMatches), null); // Dummy fill
        }

        return response()->json([
            'message' => 'Bracket sistem gugur dasar untuk ' . $count . ' peserta berhasil dibuat.',
        ]);
    }

    // Untuk tepat 6 peserta lanjut generate bagan lengkap
    $selected = collect($participantIds)->slice(0, 6)->values();
    $queue = $selected->toArray();
    $idealSlot = 8;
    $maxRound = 3;

    // BYE untuk posisi pertama dan terakhir (6 peserta)
    $entryIndexes = [0, 1, 3, 4, 6, 7];
    $fullSlots = array_fill(0, $idealSlot, null);
    foreach ($queue as $i => $pid) {
        if (isset($entryIndexes[$i])) {
            $fullSlots[$entryIndexes[$i]] = $pid;
        }
    }

    $matchNumber = 1;
    $matchMap = [];
    $matchRefs = [];

    // ROUND 1 - 1/4 Final
    for ($i = 0; $i < $idealSlot; $i += 2) {
        $p1 = $fullSlots[$i] ?? null;
        $p2 = $fullSlots[$i + 1] ?? null;
        $winner = ($p1 && !$p2) ? $p1 : (($p2 && !$p1) ? $p2 : null);

        $matchId = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId,
            'round' => 1,
            'round_label' => $this->getRoundLabel(1, $maxRound),
            'match_number' => $matchNumber,
            'participant_1' => $p1,
            'participant_2' => $p2,
            'winner_id' => $winner,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $matchMap[] = [
            'id' => $matchId,
            'winner' => $winner,
            'match_number' => $matchNumber,
            'participant_1' => $p1,
            'participant_2' => $p2,
        ];

        $matchRefs[$matchNumber] = [
            'id' => $matchId,
            'next_match_number' => null,
        ];

        $matchNumber++;
    }

    // ROUND 2 - Semifinal
    $currentRound = 2;
    $nextRound = [];
    foreach (array_chunk($matchMap, 2) as $pair) {
        $m1 = $pair[0];
        $m2 = $pair[1] ?? ['id' => null, 'winner' => null, 'match_number' => null, 'participant_1' => null, 'participant_2' => null];

        $p1 = $m1['winner'];
        $p2 = $m2['winner'];
        $winner = ($p1 && !$p2) ? $p1 : (($p2 && !$p1) ? $p2 : null);

        $matchId = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId,
            'round' => $currentRound,
            'round_label' => $this->getRoundLabel($currentRound, $maxRound),
            'match_number' => $matchNumber,
            'participant_1' => $p1,
            'participant_2' => $p2,
            'winner_id' => $winner,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($m1['id']) {
            DB::table('tournament_matches')->where('id', $m1['id'])->update(['next_match_id' => $matchId]);
            $matchRefs[$m1['match_number']]['next_match_number'] = $matchNumber;
        }
        if ($m2['id']) {
            DB::table('tournament_matches')->where('id', $m2['id'])->update(['next_match_id' => $matchId]);
            $matchRefs[$m2['match_number']]['next_match_number'] = $matchNumber;
        }

        $nextRound[] = [
            'id' => $matchId,
            'winner' => $winner,
            'match_number' => $matchNumber,
            'participant_1' => $p1,
            'participant_2' => $p2,
        ];

        $matchRefs[$matchNumber] = [
            'id' => $matchId,
            'next_match_number' => null,
        ];

        $matchNumber++;
    }

    // ROUND 3 - Final (kosong dulu)
    $finalMatchId = DB::table('tournament_matches')->insertGetId([
        'pool_id' => $poolId,
        'round' => 3,
        'round_label' => $this->getRoundLabel(3, $maxRound),
        'match_number' => $matchNumber,
        'participant_1' => null,
        'participant_2' => null,
        'winner_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach ($nextRound as $match) {
        DB::table('tournament_matches')->where('id', $match['id'])->update(['next_match_id' => $finalMatchId]);
        $matchRefs[$match['match_number']]['next_match_number'] = $matchNumber;
    }

    $matchRefs[$matchNumber] = [
        'id' => $finalMatchId,
        'next_match_number' => null,
    ];

    return response()->json([
        'message' => 'Bracket untuk 6 peserta berhasil dibuat.',
        'match_refs' => $matchRefs,
    ]);
}







    private function generateDefaultSix($poolId, $selected)
    {
        $matchNumber = 1;
        $maxRound = 3;

        // Babak 1 - 4 pertandingan (2 bye, 2 match)
        $match1Id = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId,
            'round' => 1,
            'round_label' => $this->getRoundLabel(1, $maxRound),
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
            'round_label' => $this->getRoundLabel(1, $maxRound),
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
            'round_label' => $this->getRoundLabel(1, $maxRound),
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
            'round_label' => $this->getRoundLabel(1, $maxRound),
            'match_number' => $matchNumber++,
            'participant_1' => $selected[5]->id,
            'participant_2' => null,
            'winner_id' => $selected[5]->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Babak 2 - Semifinal
        $match5Id = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId,
            'round' => 2,
            'round_label' => $this->getRoundLabel(2, $maxRound),
            'match_number' => $matchNumber++,
            'participant_1' => $selected[0]->id, // dari match1 (bye)
            'participant_2' => null,             // pemenang match2
            'winner_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $match6Id = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId,
            'round' => 2,
            'round_label' => $this->getRoundLabel(2, $maxRound),
            'match_number' => $matchNumber++,
            'participant_1' => $selected[5]->id, // dari match4 (bye)
            'participant_2' => null,             // pemenang match3
            'winner_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Final
        $match7Id = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId,
            'round' => 3,
            'round_label' => $this->getRoundLabel(3, $maxRound),
            'match_number' => $matchNumber++,
            'participant_1' => null,
            'participant_2' => null,
            'winner_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Set next_match_id
        DB::table('tournament_matches')->where('id', $match1Id)->update(['next_match_id' => $match5Id]);
        DB::table('tournament_matches')->where('id', $match2Id)->update(['next_match_id' => $match5Id]);
        DB::table('tournament_matches')->where('id', $match3Id)->update(['next_match_id' => $match6Id]);
        DB::table('tournament_matches')->where('id', $match4Id)->update(['next_match_id' => $match6Id]);
        DB::table('tournament_matches')->where('id', $match5Id)->update(['next_match_id' => $match7Id]);
        DB::table('tournament_matches')->where('id', $match6Id)->update(['next_match_id' => $match7Id]);

        return TournamentMatch::where('pool_id', $poolId)
            ->orderBy('round')
            ->orderBy('match_number')
            ->get();
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

        DB::table('tournament_participants')->where('pool_id', $poolId)->update(['pool_id' => null]);

        $shuffled = $eligibleParticipants->shuffle()->values();
        $participantIdsToUpdate = $shuffled->pluck('tp_id');

        DB::table('tournament_participants')->whereIn('id', $participantIdsToUpdate)->update(['pool_id' => $poolId]);

        $participantIds = $shuffled->pluck('id')->shuffle()->values();
        $total = $participantIds->count();

        if ($total === 1) {
            DB::table('tournament_matches')->insert([
                'pool_id' => $poolId,
                'round' => 1,
                'round_label' => 'Final',
                'match_number' => 1,
                'participant_1' => $participantIds[0],
                'participant_2' => null,
                'winner_id' => $participantIds[0],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'message' => 'Bracket untuk 1 peserta berhasil dibuat.',
                'total_participants' => $total,
                'bracket_size' => 1,
                'total_matches' => 1,
                'rounds_generated' => 1,
            ]);
        }

        $bracketSize = pow(2, ceil(log($total, 2)));
        $maxRound = ceil(log($bracketSize, 2));
        $matchNumber = 1;
        $matches = [];
        $preliminaryMatches = $total - ($bracketSize / 2);
        $byes = ($bracketSize - $total);
        $roundMatchCounts = [];

        $getLabel = function ($round) use ($maxRound) {
            $labels = [
                0 => 'Final',
                1 => 'Semifinal',
                2 => '1/4 Final',
                3 => '1/8 Final',
                4 => '1/16 Final',
                5 => '1/32 Final',
                6 => '1/64 Final',
            ];
            $diff = $maxRound - $round;
            return $labels[$diff] ?? 'Penyisihan';
        };

        for ($round = 1; $round <= $maxRound; $round++) {
            $roundMatchCounts[$round] = $bracketSize / pow(2, $round);
        }

        $byeIndexes = [];
        for ($round = 1; $round <= $maxRound; $round++) {
            for ($i = 0; $i < $roundMatchCounts[$round]; $i++) {
                $matches[] = [
                    'pool_id' => $poolId,
                    'round' => $round,
                    'round_label' => $getLabel($round),
                    'match_number' => $matchNumber++,
                    'participant_1' => null,
                    'participant_2' => null,
                    'winner_id' => null,
                    'next_match_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        $firstRoundMatchIndexes = array_keys(array_filter($matches, fn($m) => $m['round'] === 1));
        $i = 0;

        for ($j = 0; $j < $preliminaryMatches; $j++) {
            $index = $firstRoundMatchIndexes[$j];
            $matches[$index]['participant_1'] = $participantIds[$i++] ?? null;
            $matches[$index]['participant_2'] = $participantIds[$i++] ?? null;
        }

        $byeTargets = array_slice($firstRoundMatchIndexes, $preliminaryMatches);
        foreach ($byeTargets as $index) {
            $matches[$index]['participant_1'] = $participantIds[$i++] ?? null;
            $matches[$index]['winner_id'] = $matches[$index]['participant_1'];
        }

        DB::table('tournament_matches')->insert($matches);

        $matchMap = TournamentMatch::where('pool_id', $poolId)->orderBy('match_number')->get()->keyBy('match_number');

        foreach ($matchMap as $match) {
            $round = $match->round;
            if ($round < $maxRound) {
                $nextMatches = $matchMap->where('round', $round + 1)->values();
                $index = floor(($match->match_number - 1) / 2);
                if (isset($nextMatches[$index])) {
                    $match->next_match_id = $nextMatches[$index]->id;
                    $match->save();

                    if ($match->winner_id && is_null($nextMatches[$index]->participant_1)) {
                        $nextMatches[$index]->participant_1 = $match->winner_id;
                        $nextMatches[$index]->save();
                    } elseif ($match->winner_id && is_null($nextMatches[$index]->participant_2)) {
                        $nextMatches[$index]->participant_2 = $match->winner_id;
                        $nextMatches[$index]->save();
                    }
                }
            }
        }

        return response()->json([
            'message' => 'Bracket berhasil dibuat dan peserta sudah di-assign ke pool.',
            'total_participants' => $total,
            'bracket_size' => $bracketSize,
            'total_matches' => count($matches),
            'rounds_generated' => $maxRound,
        ]);
    }



    private function getByeSlots($count, $total)
    {
        $slots = [];
        if ($count == 1) $slots[] = 0;
        elseif ($count == 2) $slots = [0, $total - 1];
        elseif ($count == 3) $slots = [0, (int) floor($total / 2), $total - 1];
        else {
            for ($i = 0; $i < $count; $i++) {
                $slots[] = (int) round($i * $total / $count);
            }
        }
        return $slots;
    }





    private function generateBracketForFive($poolId, $participants)
    {
        $matchNumber = 1;
        $maxRound = 3;

        $shuffled = $participants->shuffle()->values();
        $participantIds = $shuffled->pluck('id')->values();

        // ROUND 1 - Preliminary
        $prelimId = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId,
            'round' => 1,
            'round_label' => $this->getRoundLabel(1, $maxRound),
            'match_number' => $matchNumber++,
            'participant_1' => $participantIds[0],
            'participant_2' => $participantIds[1],
            'winner_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ROUND 2 - Semifinal 1
        $semi1 = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId,
            'round' => 2,
            'round_label' => $this->getRoundLabel(2, $maxRound),
            'match_number' => $matchNumber++,
            'participant_1' => null, // winner prelim
            'participant_2' => $participantIds[2],
            'winner_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ROUND 2 - Semifinal 2
        $semi2 = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId,
            'round' => 2,
            'round_label' => $this->getRoundLabel(2, $maxRound),
            'match_number' => $matchNumber++,
            'participant_1' => $participantIds[3],
            'participant_2' => $participantIds[4],
            'winner_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ROUND 3 - Final
        $final = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId,
            'round' => 3,
            'round_label' => $this->getRoundLabel(3, $maxRound),
            'match_number' => $matchNumber++,
            'participant_1' => null,
            'participant_2' => null,
            'winner_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 🔗 Set relasi antar match
        DB::table('tournament_matches')->where('id', $prelimId)->update([
            'next_match_id' => $semi1,
        ]);

        DB::table('tournament_matches')->where('id', $semi1)->update([
            'next_match_id' => $final,
        ]);

        DB::table('tournament_matches')->where('id', $semi2)->update([
            'next_match_id' => $final,
        ]);

        $matches = TournamentMatch::where('pool_id', $poolId)
            ->orderBy('round')
            ->orderBy('match_number')
            ->get();

        return response()->json([
            'message' => '✅ Bracket 5 peserta berhasil dibuat (3 babak).',
            'matches' => $matches
        ]);
    }

    private function generateBracketForNine($poolId, $participants)
    {
        $matchNumber = 1;
        $maxRound = 4;

        // Shuffle peserta
        $shuffled = $participants->shuffle()->values();
        $participantIds = $shuffled->pluck('id')->values();

        // Preliminary (Round 1)
        $prelim1 = $participantIds[0];
        $prelim2 = $participantIds[1];

        $preliminaryId = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId,
            'round' => 1,
            'round_label' => $this->getRoundLabel(1, $maxRound),
            'match_number' => $matchNumber++,
            'participant_1' => $prelim1,
            'participant_2' => $prelim2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 7 peserta sisanya
        $remaining = $participantIds->slice(2)->values();

        $matchIds = [];

        // 🔁 Buat 4 pertandingan round 2
        for ($i = 0; $i < 4; $i++) {
            $p1 = null;
            $p2 = null;

            if ($i === 0) {
                $p2 = $remaining[0] ?? null;
            } else {
                $p1 = $remaining[($i - 1) * 2 + 1] ?? null;
                $p2 = $remaining[($i - 1) * 2 + 2] ?? null;
            }

            $id = DB::table('tournament_matches')->insertGetId([
                'pool_id' => $poolId,
                'round' => 2,
                'round_label' => $this->getRoundLabel(2, $maxRound),
                'match_number' => $matchNumber++,
                'participant_1' => $p1,
                'participant_2' => $p2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $matchIds[] = $id;
        }

        // Semifinal (Round 3)
        $semi1 = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId,
            'round' => 3,
            'round_label' => $this->getRoundLabel(3, $maxRound),
            'match_number' => $matchNumber++,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $semi2 = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId,
            'round' => 3,
            'round_label' => $this->getRoundLabel(3, $maxRound),
            'match_number' => $matchNumber++,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Final (Round 4)
        $final = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId,
            'round' => 4,
            'round_label' => $this->getRoundLabel(4, $maxRound),
            'match_number' => $matchNumber++,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ⬇️ Hubungkan Round 2 ke Semifinal
        DB::table('tournament_matches')->where('id', $matchIds[0])->update(['next_match_id' => $semi1]);
        DB::table('tournament_matches')->where('id', $matchIds[1])->update(['next_match_id' => $semi1]);
        DB::table('tournament_matches')->where('id', $matchIds[2])->update(['next_match_id' => $semi2]);
        DB::table('tournament_matches')->where('id', $matchIds[3])->update(['next_match_id' => $semi2]);

        // ⬇️ Semifinal → Final
        DB::table('tournament_matches')->where('id', $semi1)->update(['next_match_id' => $final]);
        DB::table('tournament_matches')->where('id', $semi2)->update(['next_match_id' => $final]);

        // ⬇️ Preliminary → Match pertama round 2
        DB::table('tournament_matches')->where('id', $preliminaryId)->update([
            'next_match_id' => $matchIds[0],
        ]);

        // ⬇️ Update participant_1 match pertama (pemenang preliminary)
        DB::table('tournament_matches')->where('id', $matchIds[0])->update([
            'participant_1' => null // akan diisi nanti saat preliminary selesai
        ]);

        return response()->json(['message' => '✅ Bracket untuk 9 peserta berhasil dibuat dengan match BYE di awal.']);
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
        $maxRound = 4;

        // ROUND 1 - 6 MATCH (2 bye: M1 & M6)
        $matchIds[] = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId, 'round' => 1, 'round_label' => $this->getRoundLabel(1, $maxRound), 'match_number' => $matchNumber++,
            'participant_1' => $selected[0]->id, 'participant_2' => null, 'winner_id' => $selected[0]->id,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $matchIds[] = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId, 'round' => 1, 'round_label' => $this->getRoundLabel(1, $maxRound), 'match_number' => $matchNumber++,
            'participant_1' => $selected[1]->id, 'participant_2' => $selected[2]->id,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $matchIds[] = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId, 'round' => 1, 'round_label' => $this->getRoundLabel(1, $maxRound), 'match_number' => $matchNumber++,
            'participant_1' => $selected[3]->id, 'participant_2' => $selected[4]->id,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $matchIds[] = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId, 'round' => 1, 'round_label' => $this->getRoundLabel(1, $maxRound), 'match_number' => $matchNumber++,
            'participant_1' => $selected[5]->id, 'participant_2' => $selected[6]->id,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $matchIds[] = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId, 'round' => 1, 'round_label' => $this->getRoundLabel(1, $maxRound), 'match_number' => $matchNumber++,
            'participant_1' => $selected[7]->id, 'participant_2' => $selected[8]->id,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $matchIds[] = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId, 'round' => 1, 'round_label' => $this->getRoundLabel(1, $maxRound), 'match_number' => $matchNumber++,
            'participant_1' => $selected[9]->id, 'participant_2' => null, 'winner_id' => $selected[9]->id,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // ROUND 2 - 4 MATCH
        $matchIds[] = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId, 'round' => 2, 'round_label' => $this->getRoundLabel(2, $maxRound), 'match_number' => $matchNumber++,
            'participant_1' => $selected[0]->id, // winner M1 (bye)
            'participant_2' => null,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $matchIds[] = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId, 'round' => 2, 'round_label' => $this->getRoundLabel(2, $maxRound), 'match_number' => $matchNumber++,
            'participant_1' => null, 'participant_2' => null,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $matchIds[] = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId, 'round' => 2, 'round_label' => $this->getRoundLabel(2, $maxRound), 'match_number' => $matchNumber++,
            'participant_1' => null, 'participant_2' => $selected[9]->id, // winner M6 (bye)
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $matchIds[] = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId, 'round' => 2, 'round_label' => $this->getRoundLabel(2, $maxRound), 'match_number' => $matchNumber++,
            'participant_1' => null, 'participant_2' => null,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // ROUND 3 - Semifinal
        $matchIds[] = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId, 'round' => 3, 'round_label' => $this->getRoundLabel(3, $maxRound), 'match_number' => $matchNumber++,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $matchIds[] = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId, 'round' => 3, 'round_label' => $this->getRoundLabel(3, $maxRound), 'match_number' => $matchNumber++,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // FINAL
        $matchIds[] = DB::table('tournament_matches')->insertGetId([
            'pool_id' => $poolId, 'round' => 4, 'round_label' => $this->getRoundLabel(4, $maxRound), 'match_number' => $matchNumber++,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // RELASI NEXT MATCH
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
            10 => 12, // M11 -> FINAL
            11 => 12, // M12 -> FINAL
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

        $maxParticipantCount = (int) $matchChart;
        $selectedParticipants = $participants->slice(0, $maxParticipantCount)->values();
        $participantIds = $selectedParticipants->pluck('id')->toArray();

        TournamentParticipant::whereIn('team_member_id', $participantIds)
            ->where('tournament_id', $tournamentId)
            ->update(['pool_id' => $poolId]);

        $totalRounds = (int) log($matchChart, 2);
        $matchNumber = 1;
        $matches = collect();

        for ($round = 1; $round <= $totalRounds; $round++) {
            $matchCount = $matchChart / pow(2, $round);
            $roundLabel = $this->getRoundLabel($round, $totalRounds);

            for ($i = 0; $i < $matchCount; $i++) {
                $matches->push([
                    'pool_id' => $poolId,
                    'round' => $round,
                    'round_label' => $roundLabel,
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


    public function getMatches($poolId)
    {
        $pool = Pool::findOrFail($poolId);
        $matchChart = (int) $pool->match_chart;

        $matches = TournamentMatch::where('pool_id', $poolId)
            ->with(['participantOne.contingent', 'participantTwo.contingent', 'winner'])
            ->orderBy('round')
            ->orderBy('id')
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
            'pool.categoryClass.ageCategory:id,name',
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

        // ✅ Filter opsional
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

        // ✅ Ambil dan group match
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

            $rounds = $roundGroups->map(function ($matchesInRound, $round) {
                $first = $matchesInRound->first();
                $roundLabel = $first->round_label;

                // ✅ Filter BYE match berdasarkan participant_1 / participant_2
                $filtered = $matchesInRound->filter(function ($match) use ($roundLabel) {
                    $isFirstRound = $match->round == 1;
                    $isNotFinal = strtolower(trim($roundLabel)) !== 'final';
                    $hasBye = is_null($match->participant_1) || is_null($match->participant_2);

                    return !($isFirstRound && $isNotFinal && $hasBye);
                });

                return [
                    'round' => (int) $round,
                    'round_label' => $roundLabel,
                    'matches' => $filtered->values()
                ];
            })->filter(function ($round) {
                return $round['matches']->isNotEmpty(); // ✅ Jangan kirim round kosong
            })->values();

            return [
                'pool_id' => $poolId,
                'pool_name' => $pool->name,
                'match_category_id' => $pool->matchCategory->id ?? null,
                'class_name' => $pool->categoryClass->name ?? '-',
                'age_category_id' => $pool->categoryClass->age_category_id ?? null,
                'age_category_name' => $pool->categoryClass->ageCategory->name ?? '-',
                'gender' => $pool->categoryClass->gender ?? null,
                'rounds' => $rounds
            ];
        })->values();

        return response()->json([
            'message' => 'List pertandingan berhasil diambil',
            'data' => $data
        ]);
    }


    public function listMatches_backup(Request $request, $tournamentId)
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
           

           $rounds = $roundGroups->map(function ($matchesInRound, $round) {
                return [
                    'round' => (int) $round,
                    'round_label' => $matchesInRound->first()->round_label,
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
        $rounds = \App\Models\TournamentMatch::query()
            ->select('tournament_matches.round', 'tournament_matches.round_label')
            ->join('pools', 'pools.id', '=', 'tournament_matches.pool_id')
            ->where('pools.tournament_id', $tournamentId)
            ->whereNotNull('tournament_matches.round_label')
            ->orderBy('tournament_matches.round') // ← boleh karena round disertakan di SELECT
            ->get();

        if ($rounds->isEmpty()) {
            return response()->json([]);
        }

        // Ambil unique label, dan jadikan key & value = round_label
        $filteredLabels = $rounds->unique('round_label')->mapWithKeys(function ($item) {
            return [$item->round_label => $item->round_label];
        });

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

    public function getNextPoolByTournament(Request $request)
    {
        $request->validate([
            'current_pool_id' => 'required|exists:pools,id',
        ]);

        // Ambil pool sekarang
        $currentPool = Pool::findOrFail($request->current_pool_id);

        // Ambil semua pool dalam turnamen yang sama
        $pools = Pool::where('tournament_id', $currentPool->tournament_id)
            ->orderBy('id') // ganti ke 'order' kalau ada urutan custom
            ->select('id', 'name', 'tournament_id')
            ->get()
            ->values();

        // Cari index pool sekarang
        $currentIndex = $pools->search(fn($pool) => $pool->id == $currentPool->id);

        if ($currentIndex === false || $currentIndex + 1 >= $pools->count()) {
            return response()->json(['message' => 'Tidak ada pool selanjutnya'], 404);
        }

        // Return pool berikutnya
        return response()->json($pools[$currentIndex + 1]);
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
