<?php

namespace App\Http\Controllers;

use App\Models\TournamentDrawing;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;


class TournamentDrawingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * GET /api/tournament-drawings
     * params:
     *  - page
     *  - perPage
     *  - search
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->get('perPage', 10);
        if ($perPage <= 0) {
            $perPage = 10;
        }

        $search = trim((string) $request->get('search', ''));

        $query = TournamentDrawing::with('tournament')
            ->orderByDesc('created_at');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('tournament', function ($qt) use ($search) {
                      $qt->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $paginator = $query->paginate($perPage);

        // default Laravel paginator udah sesuai sama yang lu destructure di Vue:
        // current_page, last_page, data, next_page_url, prev_page_url, dll
        return response()->json($paginator);
    }

    /**
     * Store a newly created resource in storage.
     *
     * POST /api/tournament-drawings
     */
    public function store(Request $request)
    {
        $validated = $this->validateData($request);

        // kalau format nggak pakai grup, kosongkan group_size
        if (!$this->usesGroupStage($validated['format'])) {
            $validated['group_size'] = null;
        }

        $drawing = TournamentDrawing::create($validated);

        return response()->json([
            'message' => 'Tournament drawing created successfully.',
            'data'    => $drawing->load('tournament'),
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * GET /api/tournament-drawings/{id}
     */
    public function show(TournamentDrawing $tournamentDrawing)
    {
        // Vue expect: response.data.data
        return response()->json([
            'data' => $tournamentDrawing->load('tournament'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * PUT /api/tournament-drawings/{id}
     */
    public function update(Request $request, TournamentDrawing $tournamentDrawing)
    {
        $validated = $this->validateData($request, $tournamentDrawing->id);

        if (!$this->usesGroupStage($validated['format'])) {
            $validated['group_size'] = null;
        }

        $tournamentDrawing->update($validated);

        return response()->json([
            'message' => 'Tournament drawing updated successfully.',
            'data'    => $tournamentDrawing->fresh()->load('tournament'),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * DELETE /api/tournament-drawings/{id}
     */
    public function destroy(TournamentDrawing $tournamentDrawing)
    {
        $tournamentDrawing->delete();

        return response()->json([
            'message' => 'Tournament drawing deleted successfully.',
        ]);
    }

    /**
     * Common validation.
     */
    protected function validateData(Request $request, ?int $ignoreId = null): array
    {
        $formats = ['league', 'group', 'knockout', 'group_knockout'];

        return $request->validate([
            'tournament_id' => [
                'required',
                'integer',
                'exists:tournaments,id',
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'format' => [
                'required',
                'string',
                Rule::in($formats),
            ],
            'group_size' => [
                'nullable',
                'integer',
                'min:2',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
            // future proof: config JSON bisa dipakai belakangan
            'config' => [
                'nullable',
                'array',
            ],
        ]);
    }

    /**
     * Check if a format uses group stage logic.
     */
    protected function usesGroupStage(string $format): bool
    {
        return in_array($format, ['league', 'group', 'group_knockout'], true);
    }
}
