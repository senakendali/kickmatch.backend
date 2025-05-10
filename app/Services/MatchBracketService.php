<?php

namespace App\Services;

use App\Models\Pool;
use App\Models\TournamentMatch;
use Illuminate\Support\Facades\DB;

class MatchBracketService
{
    public function generateForPool($poolId)
    {
        $pool = Pool::find($poolId);

        if (!$pool) {
            throw new \Exception('Pool tidak ditemukan');
        }

        $tournamentId = $pool->tournament_id;
        $matchChart = (int) $pool->match_chart;

        $existing = TournamentMatch::where('pool_id', $poolId)->pluck('participant_1')->merge(
            TournamentMatch::where('pool_id', $poolId)->pluck('participant_2')
        )->unique();

        $participants = DB::table('tournament_participants')
            ->join('team_members', 'tournament_participants.team_member_id', '=', 'team_members.id')
            ->where('tournament_id', $tournamentId)
            ->whereNotIn('team_members.id', $existing)
            ->select('team_members.id', 'team_members.name')
            ->get()
            ->shuffle();

        if ($participants->isEmpty()) {
            return;
        }

        return match (true) {
            $matchChart === 0 => $this->generateFullPrestasi($poolId, $participants),
            $matchChart === 6 => $this->generateSixBracket($poolId, $participants),
            $matchChart === 2 => $this->generateSingleRound($poolId, $participants),
            default => $this->generateSingleElimination($tournamentId, $poolId, $participants, $matchChart),
        };
    }

    // TODO: Tambahkan method generateFullPrestasi(), generateSixBracket(), generateSingleRound(), generateSingleElimination()
}
