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
        // Set batas maksimal upload (misalnya 100MB)
        ini_set('upload_max_filesize', '100M');
        ini_set('post_max_size', '100M');
        try {
            $validated = $request->validate([
                'name' => 'required|string',
                'tournament_document' => 'required|file|mimes:pdf,doc,docx',
                'tournament_image' => 'required|image|mimes:jpeg,png,jpg',
                'status' => 'required|string',
                'description' => 'required|string',
                'location' => 'required|string',
                'technical_meeting_date' => 'required|date',
                'start_date' => 'required|date',
                'end_date' => 'required|date',
            ]);

            // Generate slug dari name
            $slug = Str::slug($request->name);

            // Pastikan slug unik
            $originalSlug = $slug;
            $counter = 1;
            while (\App\Models\Tournament::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter++;
            }

            // Pastikan direktori tujuan ada (opsional, Laravel akan buat otomatis saat menyimpan file)
            if (!Storage::disk('public')->exists('uploads/tournament_documents')) {
                Storage::disk('public')->makeDirectory('uploads/tournament_documents');
            }
            

            if (!Storage::disk('public')->exists('uploads/tournament_images')) {
                Storage::disk('public')->makeDirectory('uploads/tournament_images');
            }

            // Simpan file dokumen & gambar
            $documentPath = $request->file('tournament_document')->store('uploads/tournament_documents', 'public');
            $imagePath = $request->file('tournament_image')->store('uploads/tournament_images', 'public');


            // Simpan ke database
            $tournament = Tournament::create([
                'name' => $request->name,
                'slug' => $slug,
                'document' => $documentPath,
                'image' => $imagePath,
                'status' => $request->status,
                'description' => $request->description,
                'location' => $request->location,
                'technical_meeting_date' => $request->technical_meeting_date,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ]);

            return response()->json($tournament, 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to create tournament', 'error' => $e->getMessage()], 500);
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
