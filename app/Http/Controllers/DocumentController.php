<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class DocumentController extends Controller
{
    public function download(Request $request)
    {
        try {
            $filename = $request->query('filename'); // ex: uploads/tournament_documents/namafile.pdf

            if (!$filename) {
                Log::error("Download failed: filename not provided.");
                return response()->json(['message' => 'Filename is required.'], 400);
            }

            $filePath = storage_path('app/public/' . $filename);

            if (!file_exists($filePath)) {
                Log::error("Download failed: File not found.", [
                    'requested_filename' => $filename,
                    'checked_path' => $filePath,
                ]);
                return response()->json([
                    'message' => 'File not found!',
                    'checked_path' => $filePath,
                ], 404);
            }

            Log::info("Download success: File found.", [
                'path' => $filePath
            ]);

            return response()->download($filePath, basename($filename), [
                'Content-Type' => mime_content_type($filePath),
            ]);
        } catch (\Exception $e) {
            Log::error("Download failed with exception.", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'An error occurred while downloading the file.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}