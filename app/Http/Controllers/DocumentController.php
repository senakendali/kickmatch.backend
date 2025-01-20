<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class DocumentController extends Controller
{
    public function download($filename)
    {
        // Define the path to the file in the public directory
        $filePath = public_path('document/' . $filename);

        // Check if the file exists
        if (!file_exists($filePath)) {
            return response()->json([
                'message' => 'File not found!'
            ], 404);
        }

        // Return the file as a response for download
        return Response::download($filePath, $filename, [
            'Content-Type' => mime_content_type($filePath)
        ]);
    }
}