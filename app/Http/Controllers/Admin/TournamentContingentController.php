<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TournamentContingent;
use App\Models\Tournament;
use App\Models\AgeCategory;
use App\Models\Contingent;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TournamentContingentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'contingent_id' => ['required', 'exists:contingents,id'],
            'tournament_id' => ['required', 'exists:tournaments,id'],
            'age_category_id' => ['required', 'exists:age_categories,id'],
            'gender' => [
                'required',
                Rule::in(['male', 'female', 'mixed']),
            ],
        ]);

        // Cek apakah tournament memang buka kategori umur & gender ini (kalau sudah ada tabel penghubung bisa dicek di sini)

        // Cek duplikat
        $exists = TournamentContingent::where($validated)->exists();
        if ($exists) {
            return response()->json([
                'message' => 'Team already joined this tournament in the selected category.',
            ], 422);
        }

        $tc = TournamentContingent::create($validated);

        return response()->json([
            'message' => 'Team successfully joined the tournament.',
            'data'    => $tc,
        ]);
    }

    /**
     * Untuk form: ambil data initial (contingent + list tournament active)
     */
    public function formData($contingentId)
    {
        $contingent = Contingent::findOrFail($contingentId);

        // misal hanya tournament aktif yg bisa dipilih
        $tournaments = Tournament::where('status', 'active')
            ->orderBy('start_date')
            ->get(['id', 'name', 'start_date']);

        return response()->json([
            'contingent' => $contingent,
            'tournaments' => $tournaments,
        ]);
    }

    /**
     * Ambil age categories untuk 1 tournament (kalau sudah punya tabel pivot tournament_age_categories)
     */
    public function ageCategoriesByTournament($tournamentId)
    {
        // kalau punya tabel tournament_age_categories:
        // $ageCategories = AgeCategory::whereHas('tournamentAgeCategories', function ($q) use ($tournamentId) {
        //     $q->where('tournament_id', $tournamentId);
        // })->get(['id','name']);

        // sementara versi simple: ambil semua
        $ageCategories = AgeCategory::orderBy('min_age')->get(['id', 'name']);

        return response()->json([
            'age_categories' => $ageCategories,
        ]);
    }
}
