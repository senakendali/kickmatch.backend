<?php

namespace App\Http\Controllers;
use App\Models\Blog;
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

class BlogSettingController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10); // default 10 per page
            $search = $request->input('search', ''); // parameter pencarian
            $query = Blog::query();
    
            // Filter jika ada keyword pencarian
            if (!empty($search)) {
                $query->where('title', 'like', '%' . $search . '%');
            }
    
            // Paginate hasil query yang sudah difilter
            $blogs = $query->paginate($perPage);
    
            return response()->json($blogs);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch blogs',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function show($id)
    {
        try {
            $blog = Blog::findOrFail($id);

            $image = $blog->cover_image ? asset('storage/' . $blog->cover_image) : null;

            $result = $blog->toArray();
            $result['cover_image'] = $image;

            // Format tanggal published_at ke dd/mm/yyyy jika ada
            if (!empty($blog->published_at)) {
                $result['published_at'] = Carbon::parse($blog->published_at)->format('d/m/Y');
            }

            return response()->json($result);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Blog not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to retrieve blog', 'error' => $e->getMessage()], 500);
        }
    }


    public function store(Request $request)
    {

        $input = $request->all();

        // Set batas maksimal upload (misalnya 100MB)
        ini_set('upload_max_filesize', '100M');
        ini_set('post_max_size', '100M');
        try {
            $validated = $request->validate([
                'title' => 'required|string',
                'excerpt' => 'required|string',
                'content' => 'required|string',
                'category_id' => 'required|string',
                'cover_image' => 'required|image|mimes:jpeg,png,jpg',
                'status' => 'required|string',
            ]);

            // Generate slug dari name
            $slug = Str::slug($request->title);

            // Pastikan slug unik
            $originalSlug = $slug;
            $counter = 1;
            while (\App\Models\Blog::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter++;
            }

            if (!Storage::disk('public')->exists('uploads/blog_images')) {
                Storage::disk('public')->makeDirectory('uploads/blog_images');
            }

            // Simpan file dokumen & gambar
            $imagePath = $request->file('cover_image')->store('uploads/blog_images', 'public');


            // Simpan ke database
            $blog = Blog::create([
                'title' => $request->title,
                'slug' => $slug,
                'excerpt' => $request->excerpt,
                'content' => $request->content,
                'author_id' => $request->author_id,
                'is_pinned' => $request->is_pinned  ,
                'meta_title' => $request->meta_title,
                'meta_description' => $request->meta_description,
                'meta_keywords' => $request->meta_keywords,
                'category_id' => $request->category_id,
                'published_at' => $request->published_at,
                'cover_image' => $imagePath,
                'status' => $request->status,
            ]);

            return response()->json($blog, 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to create blog', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        // Set batas maksimal upload (misalnya 100MB)
        ini_set('upload_max_filesize', '100M');
        ini_set('post_max_size', '100M');

        try {
            $blog = Blog::findOrFail($id);

            $validated = $request->validate([
                'title' => 'required|string',
                'excerpt' => 'required|string',
                'content' => 'required|string',
                'category_id' => 'required|string',
                'cover_image' => 'image|mimes:jpeg,png,jpg',
                'status' => 'required|string',
                'meta_title' => 'string',
                'meta_description' => 'string',
                'meta_keywords' => 'string',
                'slug' => 'string',
                'author_id' => 'string',
                'is_pinned' => 'string',
            ]);

            // Handle slug jika name berubah
            if ($request->has('title')) {
                $slug = Str::slug($request->title);
                $originalSlug = $slug;
                $counter = 1;

                while (\App\Models\Blog::where('slug', $slug)->where('id', '!=', $blog->id)->exists()) {
                    $slug = $originalSlug . '-' . $counter++;
                }

                $validated['slug'] = $slug;
            }

            if (!Storage::disk('public')->exists('uploads/blog_images')) {
                Storage::disk('public')->makeDirectory('uploads/blog_images');
            }

            if ($request->hasFile('cover_image')) {
                $imagePath = $request->file('cover_image')->store('uploads/blog_images', 'public');
                $validated['cover_image'] = $imagePath;
            }

            $blog->update($validated);

            return response()->json($blog);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Blog not found'], 404);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to update blog', 'error' => $e->getMessage()], 500);
        }
    }


    public function destroy($id){
        try {
            $blog = Blog::findOrFail($id);
            $blog->delete();
            return response()->json(['message' => 'Blog deleted successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Blog not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete blog', 'error' => $e->getMessage()], 500);
        }
    }
}
