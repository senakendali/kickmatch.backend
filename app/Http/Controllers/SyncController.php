<?php

namespace App\Http\Controllers;

use App\Models\TournamentMatch;
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

                // Cek apakah participant_1 ada, jika tidak set sebagai 'TBD'
                'red_id' => $match->participantOne ? $match->participantOne->id : null,
                'red_name' => $match->participantOne ? $match->participantOne->name : 'TBD',
                'red_contingent' => $match->participantOne ? $match->participantOne->contingent?->name : 'TBD',

                // Cek apakah participant_2 ada, jika tidak set sebagai 'TBD'
                'blue_id' => $match->participantTwo ? $match->participantTwo->id : null,
                'blue_name' => $match->participantTwo ? $match->participantTwo->name : 'TBD',
                'blue_contingent' => $match->participantTwo ? $match->participantTwo->contingent?->name : 'TBD',

                // Menambahkan parent match id, jika tidak ada maka null
                'parent_match_red_id' => $parents[0] ?? null,
                'parent_match_blue_id' => $parents[1] ?? null,
            ];
        });

        return response()->json($result);
    }

}
