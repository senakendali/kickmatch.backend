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
use App\Models\TournamentPermit;


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
            // support ?per_page= atau ?perPage=
            $perPage = $request->get('per_page', $request->get('perPage', 10));
            $search  = $request->input('search', '');

            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $query = Tournament::query();

            // ===== FILTER BERDASARKAN ROLE =====
            $roleId = (int) ($user->role_id ?? 0);

            // HANYA EO yang di-limit ke turnamen miliknya
            if ($roleId === 2) { // 2 = EO (sesuaikan kalau di sistem lu beda)
                $organizerId = EventOrganizer::where('user_id', $user->id)->value('id');

                if ($organizerId) {
                    $query->where('organizer_id', $organizerId);
                } else {
                    // EO belum punya organizer profile → kosongin hasil
                    $query->whereRaw('1 = 0');
                }
            }
            // Owner / admin / role lain: ga perlu where organizer_id

            // ===== SEARCH FILTER =====
            if (!empty($search)) {
                $query->where('name', 'like', '%' . $search . '%');
            }

            // ===== PAGINATE =====
            $tournaments = $query->paginate($perPage);

            return response()->json($tournaments);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch tournaments',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    

    public function show($id)
    {
        try {
            // eager load permit biar 1x query
            $tournament = Tournament::with('permit')->findOrFail($id);

            // base array dari model
            $result = $tournament->toArray();

            // URL dokumen utama & image
            $result['document'] = $tournament->document
                ? asset('storage/' . $tournament->document)
                : null;

            $result['image'] = $tournament->image
                ? asset('storage/' . $tournament->image)
                : null;

            // kalau lu pakai rules_document juga, sekalian expose URL-nya
            $result['rules_document'] = $tournament->rules_document
                ? asset('storage/' . $tournament->rules_document)
                : null;

            // blok permit (kalau ada)
            if ($tournament->permit) {
                $permit = $tournament->permit;

                $result['permit'] = [
                    'id'               => $permit->id,
                    'permit_type'      => $permit->permit_type,
                    'permit_number'    => $permit->permit_number,
                    'issuer'           => $permit->issuer,
                    'issued_at'        => $permit->issued_at,
                    'expired_at'       => $permit->expired_at,
                    'status'           => $permit->status,
                    'rejection_reason' => $permit->rejection_reason,
                    'document_url'     => $permit->document_path
                        ? asset('storage/' . $permit->document_path)
                        : null,
                    'reviewed_by'      => $permit->reviewed_by,
                    'reviewed_at'      => $permit->reviewed_at,
                ];
            } else {
                $result['permit'] = null;
            }

            return response()->json($result);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Tournament not found'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve tournament',
                'error'   => $e->getMessage(),
            ], 500);
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

                // PERMIT DETAIL
                'permit_type'        => 'nullable|string|max:100',
                'permit_number'      => 'nullable|string|max:255',
                'permit_issuer'      => 'nullable|string|max:255',
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
                'organizer_id'         => $eventOrganizerId,
                'created_by'           => $user->id,

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

            // --- Tournament Permit (NEW) ---
            if ($requiresPermit) {
                $permitStatus = $submitNow ? 'submitted' : 'draft';

                TournamentPermit::create([
                    'tournament_id'  => $tournament->id,
                    'permit_type'    => $validated['permit_type'] ?? null,
                    'permit_number'  => $validated['permit_number'] ?? null,
                    'issuer'         => $validated['permit_issuer'] ?? null,
                    'issued_at'      => $validated['permit_issued_at'] ?? null,
                    'expired_at'     => $validated['permit_expired_at'] ?? null,
                    'document_path'  => $permitPath,
                    'status'         => $permitStatus,
                    'rejection_reason' => null,
                    'reviewed_by'    => null,
                    'reviewed_at'    => null,
                ]);
            }

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
        ini_set('upload_max_filesize', '100M');
        ini_set('post_max_size', '100M');

        try {
            $tournament = Tournament::findOrFail($id);

            // VALIDATION – samain sama store()
            $validated = $request->validate([
                // BASIC
                'name'        => 'required|string|max:255',
                'slug'        => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'status'      => 'required|in:active,inactive',
                'is_highlight'=> 'nullable',

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

                // PERMIT DETAIL
                'permit_type'        => 'nullable|string|max:100',
                'permit_number'      => 'nullable|string|max:255',
                'permit_issuer'      => 'nullable|string|max:255',
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

            // --- SLUG: unik, tapi skip diri sendiri ---
            $slug = $validated['slug'] ?? Str::slug($validated['name']);
            $originalSlug = $slug;
            $counter = 1;

            while (
                Tournament::where('slug', $slug)
                    ->where('id', '!=', $tournament->id)
                    ->exists()
            ) {
                $slug = $originalSlug . '-' . $counter++;
            }

            // --- DIRECTORIES ---
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

            // --- FILE HANDLING ---
            if ($request->hasFile('tournament_document')) {
                if ($tournament->document && Storage::disk('public')->exists($tournament->document)) {
                    Storage::disk('public')->delete($tournament->document);
                }

                $documentPath = $request->file('tournament_document')
                    ->store('uploads/tournament_documents', 'public');
                $tournament->document = $documentPath;
            }

            if ($request->hasFile('rules_document')) {
                if ($tournament->rules_document && Storage::disk('public')->exists($tournament->rules_document)) {
                    Storage::disk('public')->delete($tournament->rules_document);
                }

                $rulesPath = $request->file('rules_document')
                    ->store('uploads/tournament_rules', 'public');
                $tournament->rules_document = $rulesPath;
            }

            if ($request->hasFile('tournament_image')) {
                if ($tournament->image && Storage::disk('public')->exists($tournament->image)) {
                    Storage::disk('public')->delete($tournament->image);
                }

                $imagePath = $request->file('tournament_image')
                    ->store('uploads/tournament_images', 'public');
                $tournament->image = $imagePath;
            }

            $permitDocumentPath = null;
            if ($request->hasFile('permit_document')) {
                $permitDocumentPath = $request->file('permit_document')
                    ->store('uploads/tournament_permits', 'public');
            }

            // --- Boolean & flow ---
            $isHighlight    = $request->boolean('is_highlight');
            $requiresPermit = $request->boolean('requires_permit');
            $submitNow      = $request->boolean('submit_now');

            if ($submitNow && $tournament->approval_status === 'draft') {
                $tournament->approval_status = 'submitted';
                $tournament->submitted_at    = now();
            }

            // --- UPDATE FIELDS ---
            $tournament->tournament_format_id   = $validated['tournament_format_id'];
            $tournament->name                   = $validated['name'];
            $tournament->slug                   = $slug;

            $tournament->status                 = $validated['status'];
            $tournament->is_highlight           = $isHighlight;

            $tournament->description            = $validated['description'] ?? null;
            $tournament->location               = $validated['location'] ?? null;
            $tournament->event_mode             = $validated['event_mode'];
            $tournament->technical_meeting_date = $validated['technical_meeting_date'] ?? null;
            $tournament->start_date             = $validated['start_date'] ?? null;
            $tournament->end_date               = $validated['end_date'] ?? null;

            $tournament->visibility             = $validated['visibility'];
            $tournament->registration_open_at   = $validated['registration_open_at'] ?? null;
            $tournament->registration_close_at  = $validated['registration_close_at'] ?? null;
            $tournament->max_teams              = $validated['max_teams'] ?? null;
            $tournament->requires_permit        = $requiresPermit;

            $tournament->save();

            // --- Tournament Permit UPSERT ---
            if ($requiresPermit) {
                $permitStatus = $submitNow ? 'submitted' : 'draft';

                $permit = TournamentPermit::where('tournament_id', $tournament->id)->first();

                if (!$permit) {
                    $permit = new TournamentPermit();
                    $permit->tournament_id = $tournament->id;
                    $permit->status        = $permitStatus;
                }

                // kalau status sebelumnya udah accepted/rejected, jangan di-downgrade kalau nggak submit
                if ($permit->status === 'draft' || $permit->status === 'submitted') {
                    $permit->status = $permitStatus;
                }

                $permit->permit_type   = $validated['permit_type'] ?? $permit->permit_type;
                $permit->permit_number = $validated['permit_number'] ?? $permit->permit_number;
                $permit->issuer        = $validated['permit_issuer'] ?? $permit->issuer;
                $permit->issued_at     = $validated['permit_issued_at'] ?? $permit->issued_at;
                $permit->expired_at    = $validated['permit_expired_at'] ?? $permit->expired_at;

                if ($permitDocumentPath) {
                    if ($permit->document_path && Storage::disk('public')->exists($permit->document_path)) {
                        Storage::disk('public')->delete($permit->document_path);
                    }
                    $permit->document_path = $permitDocumentPath;
                }

                // reviewed_by/reviewed_at/rejection_reason biar admin yang isi
                $permit->save();
            }

            return response()->json([
                'message' => 'Tournament updated successfully.',
                'data'    => $tournament->fresh(),
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Tournament not found',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update tournament',
                'error'   => $e->getMessage(),
            ], 500);
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
