<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\Request;

class BlogPostController extends Controller
{
    public function index()
    {
        // Ambil post yang status=1 (published), urut published_at terbaru, limit 9
        $posts = BlogPost::with('category')
            ->where('status', 1)
            ->orderByDesc('published_at')
            ->limit(9)
            ->get();

        // Format response sesuai Vue props yang kamu pakai
        $data = $posts->map(function($post) {
            return [
                'title' => $post->title,
                'slug' => $post->slug,
                'category' => $post->category ? $post->category->name : 'Uncategorized',
                'excerpt' => $post->excerpt,
                'image' => $post->cover_image ? asset($post->cover_image) : asset('/images/default-blog.jpg'),
                'to' => url('/blogs/' . $post->slug),
            ];
        });

        return response()->json($data);
    }

    public function show($slug){
        $blog = BlogPost::where('slug', $slug)->first();

        if (!$blog) {
            return response()->json(['message' => 'Blog not found'], 404);
        }

        return response()->json($blog);
    }
}