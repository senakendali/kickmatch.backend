<?php

namespace App\Http\Controllers;

use App\Models\MatchSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MatchScheduleController extends Controller
{
   
    public function index(Request $request)
    {
        try {
            $schedules = MatchSchedule::with(['arena', 'tournament', 'details.tournamentMatch'])
                ->paginate($request->get('per_page', 10));

            return response()->json($schedules, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to fetch match schedules', 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $schedule = MatchSchedule::with([
                'arena',
                'tournament',
                'details.tournamentMatch.participantOne.contingent',
                'details.tournamentMatch.participantTwo.contingent'
            ])->findOrFail($id);
            
            return response()->json(['data' => $schedule], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Match schedule not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred', 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'tournament_arena_id' => 'required|exists:tournament_arena,id',
            'scheduled_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'nullable',
            'note' => 'nullable|string',
            'matches' => 'required|array|min:1',
            'matches.*.tournament_match_id' => 'required|exists:tournament_matches,id',
            'matches.*.order' => 'nullable|integer',
            'matches.*.start_time' => 'nullable',
            'matches.*.note' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $schedule = MatchSchedule::create([
                'tournament_id' => $request->tournament_id,
                'tournament_arena_id' => $request->tournament_arena_id,
                'scheduled_date' => $request->scheduled_date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'note' => $request->note,
            ]);

            foreach ($request->matches as $match) {
                $schedule->details()->create([
                    'tournament_match_id' => $match['tournament_match_id'],
                    'order' => $match['order'] ?? null,
                    'start_time' => $match['start_time'] ?? null,
                    'note' => $match['note'] ?? null,
                ]);
            }

            DB::commit();

            return response()->json(['data' => $schedule->load(['details.tournamentMatch'])], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create match schedule', 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'tournament_id' => 'sometimes|exists:tournaments,id',
            'tournament_arena_id' => 'sometimes|exists:tournament_arena,id',
            'scheduled_date' => 'sometimes|date',
            'start_time' => 'sometimes',
            'end_time' => 'nullable',
            'note' => 'nullable|string',
            'matches' => 'sometimes|array',
            'matches.*.tournament_match_id' => 'required_with:matches|exists:tournament_matches,id',
            'matches.*.order' => 'nullable|integer',
            'matches.*.start_time' => 'nullable',
            'matches.*.note' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $schedule = MatchSchedule::findOrFail($id);

            $schedule->update([
                'tournament_id' => $request->tournament_id ?? $schedule->tournament_id,
                'arena_id' => $request->tournament_arena_id ?? $schedule->arena_id,
                'scheduled_date' => $request->scheduled_date ?? $schedule->scheduled_date,
                'start_time' => $request->start_time ?? $schedule->start_time,
                'end_time' => $request->end_time ?? $schedule->end_time,
                'note' => $request->note ?? $schedule->note,
            ]);

            // If matches included, reset all and insert new ones
            if ($request->has('matches')) {
                $schedule->details()->delete();

                foreach ($request->matches as $match) {
                    $schedule->details()->create([
                        'tournament_match_id' => $match['tournament_match_id'],
                        'order' => $match['order'] ?? null,
                        'start_time' => $match['start_time'] ?? null,
                        'note' => $match['note'] ?? null,
                    ]);
                }
            }

            DB::commit();

            return response()->json(['data' => $schedule->load(['details.tournamentMatch'])], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['error' => 'Match schedule not found'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to update match schedule',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function destroy($id)
    {
        try {
            $schedule = MatchSchedule::findOrFail($id);
            $schedule->details()->delete();
            $schedule->delete();

            return response()->json(['message' => 'Match schedule deleted successfully'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Match schedule not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete match schedule', 'message' => $e->getMessage()], 500);
        }
    }

}
