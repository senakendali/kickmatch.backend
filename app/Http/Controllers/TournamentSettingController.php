<?php

namespace App\Http\Controllers;
use App\Models\Tournament;
use App\Models\TournamentCategory;
use App\Models\TournamentAgeCategory;
use App\Models\AgeCategory;
use App\Models\CategoryClass;
use App\Models\TournamentClass;
use App\Models\TournamentActivity;
use App\Models\MatchCategory;
use App\Models\TournamentContingent;
use App\Models\Contingent;
use App\Models\TeamMember;
use App\Models\EventOrganizer; 

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TournamentSettingController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10); // default 10 per page
            $search = $request->input('search', ''); // parameter pencarian
            $query = Tournament::query();
    
            // Filter jika ada keyword pencarian
            if (!empty($search)) {
                $query->where('name', 'like', '%' . $search . '%');
            }
    
            // Paginate hasil query yang sudah difilter
            $tournaments = $query->paginate($perPage);
    
            return response()->json($tournaments);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch tournaments',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    

    public function show($id)
    {
        try {
            $tournament = Tournament::findOrFail($id);

            $document = $tournament->document ? asset('storage/' . $tournament->document) : null;
            $image = $tournament->image ? asset('storage/' . $tournament->image) : null;

            $result = $tournament->toArray();
            $result['document'] = $document;
            $result['image'] = $image;

            return response()->json($result);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Tournament not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to retrieve tournament', 'error' => $e->getMessage()], 500);
        }
    }


    public function store(Request $request)
    {
        ini_set('upload_max_filesize', '100M');
        ini_set('post_max_size', '100M');

        try {
            $validated = $request->validate([
                // BASIC
                'name'        => 'required|string|max:255',
                'slug'        => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'status'      => 'required|in:active,inactive',
                'is_highlight'=> 'nullable', // cast manual nanti

                // LOCATION & MODE
                'location'    => 'nullable|string|max:255',
                'event_mode'  => 'required|in:offline,online,hybrid',
                'visibility'  => 'required|in:public,unlisted,private',
                'requires_permit' => 'nullable',

                // SCHEDULE
                'technical_meeting_date' => 'nullable|date',
                'start_date'             => 'nullable|date',
                'end_date'               => 'nullable|date',
                'registration_open_at'   => 'nullable|date',
                'registration_close_at'  => 'nullable|date',
                'max_teams'              => 'nullable|integer|min:1',

                // FORMAT
                'tournament_format_id'   => 'required|integer|exists:tournament_formats,id',
                'submit_now'             => 'nullable',

                // FILES
                'tournament_document'    => 'nullable|file|mimes:pdf,doc,docx|max:10240',
                'rules_document'         => 'nullable|file|mimes:pdf|max:10240',
                'tournament_image'       => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
                'permit_document'        => 'nullable|file|mimes:pdf|max:10240',

                // PERMIT DETAIL (belum disimpan ke DB tournaments)
                'permit_type'        => 'nullable|string|max:100',
                'permit_number'      => 'nullable|string|max:191',
                'permit_issuer'      => 'nullable|string|max:191',
                'permit_issued_at'   => 'nullable|date',
                'permit_expired_at'  => 'nullable|date',
            ]);

            // --- Cross field validation ---
            if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
                if ($validated['end_date'] < $validated['start_date']) {
                    throw ValidationException::withMessages([
                        'end_date' => ['End date tidak boleh lebih kecil dari start date.'],
                    ]);
                }
            }

            if (!empty($validated['registration_open_at']) && !empty($validated['registration_close_at'])) {
                if ($validated['registration_close_at'] < $validated['registration_open_at']) {
                    throw ValidationException::withMessages([
                        'registration_close_at' => ['Registration close tidak boleh lebih kecil dari registration open.'],
                    ]);
                }
            }

            // --- User & Event Organizer ---
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            // cari event organizer berdasarkan user_id
            $eventOrganizerId = EventOrganizer::where('user_id', $user->id)->value('id');

            if (!$eventOrganizerId) {
                return response()->json([
                    'message' => 'Event organizer profile tidak ditemukan. Selesaikan onboarding EO terlebih dahulu.',
                ], 422);
            }

            // --- SLUG ---
            $slug = $validated['slug'] ?? Str::slug($validated['name']);
            $originalSlug = $slug;
            $counter = 1;

            while (Tournament::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter++;
            }

            // --- FILE HANDLING ---
            $documentPath = null;
            $imagePath    = null;
            $rulesPath    = null;
            $permitPath   = null;

            if (!Storage::disk('public')->exists('uploads/tournament_documents')) {
                Storage::disk('public')->makeDirectory('uploads/tournament_documents');
            }
            if (!Storage::disk('public')->exists('uploads/tournament_images')) {
                Storage::disk('public')->makeDirectory('uploads/tournament_images');
            }
            if (!Storage::disk('public')->exists('uploads/tournament_rules')) {
                Storage::disk('public')->makeDirectory('uploads/tournament_rules');
            }
            if (!Storage::disk('public')->exists('uploads/tournament_permits')) {
                Storage::disk('public')->makeDirectory('uploads/tournament_permits');
            }

            if ($request->hasFile('tournament_document')) {
                $documentPath = $request->file('tournament_document')
                    ->store('uploads/tournament_documents', 'public');
            }

            if ($request->hasFile('tournament_image')) {
                $imagePath = $request->file('tournament_image')
                    ->store('uploads/tournament_images', 'public');
            }

            if ($request->hasFile('rules_document')) {
                $rulesPath = $request->file('rules_document')
                    ->store('uploads/tournament_rules', 'public');
            }

            if ($request->hasFile('permit_document')) {
                $permitPath = $request->file('permit_document')
                    ->store('uploads/tournament_permits', 'public');
            }

            // --- Boolean & flow ---
            $isHighlight    = $request->boolean('is_highlight');
            $requiresPermit = $request->boolean('requires_permit');
            $submitNow      = $request->boolean('submit_now');

            $approvalStatus = 'draft';
            $submittedAt    = null;

            if ($submitNow) {
                $approvalStatus = 'submitted';
                $submittedAt    = now();
            }

            // --- Create Tournament ---
            $tournament = Tournament::create([
                'organizer_id'         => $eventOrganizerId,  // ⬅️ ID dari table event_organizers
                'created_by'           => $user->id,          // ⬅️ user yang bikin

                'tournament_format_id' => $validated['tournament_format_id'],

                'name'                 => $validated['name'],
                'slug'                 => $slug,

                'document'             => $documentPath,
                'rules_document'       => $rulesPath,
                'image'                => $imagePath,

                'status'               => $validated['status'],
                'approval_status'      => $approvalStatus,
                'is_highlight'         => $isHighlight,

                'description'          => $validated['description'] ?? null,
                'location'             => $validated['location'] ?? null,
                'event_mode'           => $validated['event_mode'],
                'technical_meeting_date' => $validated['technical_meeting_date'] ?? null,
                'start_date'           => $validated['start_date'] ?? null,
                'end_date'             => $validated['end_date'] ?? null,

                'visibility'           => $validated['visibility'],
                'registration_open_at' => $validated['registration_open_at'] ?? null,
                'registration_close_at'=> $validated['registration_close_at'] ?? null,
                'max_teams'            => $validated['max_teams'] ?? null,
                'requires_permit'      => $requiresPermit,

                'submitted_at'         => $submittedAt,
            ]);

            // TODO: kalau nanti permit mau diseriusin, simpan $permitPath + detail permit
            // ke table lain, misalnya tournament_permits.

            return response()->json([
                'message' => 'Tournament created successfully.',
                'data'    => $tournament,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create tournament',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        // Set batas maksimal upload (misalnya 100MB)
        ini_set('upload_max_filesize', '100M');
        ini_set('post_max_size', '100M');

        try {
            $tournament = Tournament::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|string',
                'tournament_document' => 'sometimes|nullable|file|mimes:pdf,doc,docx',
                'tournament_image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg',
                'status' => 'sometimes|string',
                'description' => 'sometimes|string',
                'location' => 'sometimes|string',
                'technical_meeting_date' => 'sometimes|date',
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date',
            ]);

            // Handle slug jika name berubah
            if ($request->has('name')) {
                $slug = Str::slug($request->name);
                $originalSlug = $slug;
                $counter = 1;

                while (\App\Models\Tournament::where('slug', $slug)->where('id', '!=', $tournament->id)->exists()) {
                    $slug = $originalSlug . '-' . $counter++;
                }

                $validated['slug'] = $slug;
            }

            // Buat direktori jika belum ada
            if (!Storage::disk('public')->exists('uploads/tournament_documents')) {
                Storage::disk('public')->makeDirectory('uploads/tournament_documents');
            }

            if (!Storage::disk('public')->exists('uploads/tournament_images')) {
                Storage::disk('public')->makeDirectory('uploads/tournament_images');
            }

            // Simpan file baru jika dikirim
            if ($request->hasFile('tournament_document')) {
                $documentPath = $request->file('tournament_document')->store('uploads/tournament_documents', 'public');
                $validated['document'] = $documentPath;
            }

            if ($request->hasFile('tournament_image')) {
                $imagePath = $request->file('tournament_image')->store('uploads/tournament_images', 'public');
                $validated['image'] = $imagePath;
            }

            $tournament->update($validated);

            return response()->json($tournament);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Tournament not found'], 404);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to update tournament', 'error' => $e->getMessage()], 500);
        }
    }


    public function destroy($id)
    {
        try {
            $tournament = Tournament::findOrFail($id);
            $tournament->delete();
            return response()->json(['message' => 'Tournament deleted successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Tournament not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete tournament', 'error' => $e->getMessage()], 500);
        }
    }
}
