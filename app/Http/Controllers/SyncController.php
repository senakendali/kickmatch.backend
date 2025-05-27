<?php

namespace App\Http\Controllers;

use App\Models\TournamentMatch;
use App\Models\SeniMatch;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function matches(Request $request)
    {
        $tournamentSlug = $request->query('tournament');

        // Ambil semua match yang sudah dijadwalkan
        $matches = TournamentMatch::with([
            'pool.tournament',
            'pool.categoryClass',
            'pool',
            'scheduleDetail.schedule.arena',
            'participantOne.contingent',
            'participantTwo.contingent'
        ])
        ->whereHas('scheduleDetail.schedule') // hanya match yang sudah ada jadwal + arena
        ->whereHas('pool.tournament', function ($q) use ($tournamentSlug) {
            $q->where('slug', $tournamentSlug);
        })
        ->orderBy('round')
        ->orderBy('match_number')
        ->get();

        // Mapping parent untuk parent_match_red_id & parent_match_blue_id
        $parentMatchMap = [];
        foreach ($matches as $match) {
            if ($match->next_match_id) {
                if (!isset($parentMatchMap[$match->next_match_id])) {
                    $parentMatchMap[$match->next_match_id] = [];
                }
                $parentMatchMap[$match->next_match_id][] = $match->id;
            }
        }

        $result = $matches->map(function ($match) use ($parentMatchMap) {
            // Mendapatkan parent untuk red dan blue participant
            $parents = $parentMatchMap[$match->id] ?? [];

            return [
                'match_id' => $match->id,
                'tournament' => $match->pool->tournament->name,
                'arena' => $match->scheduleDetail?->schedule?->arena?->name,
                'scheduled_date' => $match->scheduleDetail?->schedule?->scheduled_date,
                'start_time' => $match->scheduleDetail?->schedule?->start_time,
                'pool' => $match->pool->name,
                'class' => $match->pool->categoryClass->name,
                'round_level' => $match->round,
                'match_number' => $match->match_number,
                'match_duration' => $match->pool->match_duration,

                // Cek apakah participant_1 ada, jika tidak set sebagai 'TBD'
                'blue_id' => $match->participantOne ? $match->participantOne->id : null,
                'blue_name' => $match->participantOne ? $match->participantOne->name : 'TBD',
                'blue_contingent' => $match->participantOne ? $match->participantOne->contingent?->name : 'TBD',


                // Cek apakah participant_2 ada, jika tidak set sebagai 'TBD'
                'red_id' => $match->participantTwo ? $match->participantTwo->id : null,
                'red_name' => $match->participantTwo ? $match->participantTwo->name : 'TBD',
                'red_contingent' => $match->participantTwo ? $match->participantTwo->contingent?->name : 'TBD',


                // Menambahkan parent match id, jika tidak ada maka null
                'parent_match_red_id' => $parents[0] ?? null,
                'parent_match_blue_id' => $parents[1] ?? null,
            ];
        });

        return response()->json($result);
    }

   public function seniMatches(Request $request)
    {
        $tournamentSlug = $request->query('tournament');

        $matches = \App\Models\SeniMatch::with([
                'pool.tournament',
                'pool.ageCategory',
                'scheduleDetail.schedule.arena',
                'contingent',
            ])
            ->whereHas('pool.tournament', function ($q) use ($tournamentSlug) {
                $q->where('slug', $tournamentSlug);
            })
            ->get();

        $result = $matches->map(function ($match) {
            // Ambil nama peserta langsung dari kolom team_member_X
            $nama1 = \App\Models\TeamMember::find($match->team_member_1)?->name;
            $nama2 = \App\Models\TeamMember::find($match->team_member_2)?->name;
            $nama3 = \App\Models\TeamMember::find($match->team_member_3)?->name;

            return [
                'remote_match_id' => $match->id,
                'remote_contingent_id' => $match->contingent_id,
                'remote_team_member_1' => $match->team_member_1,
                'remote_team_member_2' => $match->team_member_2,
                'remote_team_member_3' => $match->team_member_3,

                'tournament_name' => $match->pool->tournament->name,
                'arena_name' => $match->scheduleDetail?->schedule?->arena?->name,
                'match_date' => $match->scheduleDetail?->schedule?->scheduled_date,
                'match_time' => $match->scheduleDetail?->schedule?->start_time,
                'pool_name' => $match->pool->name,
                'match_order' => $match->match_order,

                'category' => match ($match->match_category_id) {
                    2 => 'Tunggal',
                    3 => 'Ganda',
                    4 => 'Regu',
                    5 => 'Solo Kreatif',
                    default => 'Unknown',
                },
                'match_type' => match ($match->match_category_id) {
                    2 => 'seni_tunggal',
                    3 => 'seni_ganda',
                    4 => 'seni_regu',
                    5 => 'solo_kreatif',
                    default => 'unknown',
                },
                'gender' => $match->gender,
                'contingent_name' => $match->contingent?->name ?? 'TBD',

                'participant_1' => $nama1,
                'participant_2' => $nama2,
                'participant_3' => $nama3,

                'age_category' => $match->pool->ageCategory->name,
                'final_score' => null,
            ];
        });

        return response()->json($result);
    }


    public function updateTandingMatchStatus(Request $request)
    {
        $validated = $request->validate([
            'remote_match_id' => 'required|integer',
            'status' => 'required|in:not_started,in_progress,finished',
            'participant_1_score' => 'nullable|numeric',
            'participant_2_score' => 'nullable|numeric',
            'winner_id' => 'nullable|integer',
        ]);

        TournamentMatch::where('id', $validated['remote_match_id'])
            ->update([
                'status' => $validated['status'],
                'participant_1_score' => $validated['participant_1_score'] ?? 0,
                'participant_2_score' => $validated['participant_2_score'] ?? 0,
                'winner_id' => $validated['winner_id'] ?? null,
            ]);

        return response()->json(['message' => 'Status updated']);
    }

    public function updateSeniMatchStatus(Request $request)
    {
        $validated = $request->validate([
            'remote_match_id' => 'required|integer',
            'status' => 'required|in:not_started,ongoing,finished',
            'final_score' => 'nullable|numeric',
        ]);

        SeniMatch::where('id', $validated['remote_match_id'])
            ->update([
                'status' => $validated['status'],
                'final_score' => $validated['final_score'] ?? 0,
            ]);

        return response()->json(['message' => 'Status updated']);
    }

    public function updateNextMatchSlot(Request $request)
    {
        $request->validate([
            'remote_match_id' => 'required|integer|exists:tournament_matches,id',
            'slot' => 'required|in:1,2', // 1 = biru, 2 = merah
            'winner_id' => 'required|integer',
        ]);

        $match = TournamentMatch::findOrFail($request->remote_match_id);

        if ($request->slot == 1) {
            // 🟦 Slot biru
            $match->participant_1 = $request->winner_id;
        } elseif ($request->slot == 2) {
            // 🔴 Slot merah
            $match->participant_2 = $request->winner_id;
        }

        $match->save();

        return response()->json([
            'message' => 'Peserta berhasil dimasukkan ke pertandingan selanjutnya.',
            'match_id' => $match->id,
            'slot' => $request->slot,
            'participant_id' => $request->winner_id,
        ]);
    }







}
