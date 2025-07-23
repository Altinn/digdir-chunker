<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function getImage(Request $request, string $uuid, string $filename): StreamedResponse
    {
        $path = "files/{$uuid}/images/{$filename}";

        if (!Storage::exists($path)) {
            abort(404);
        }

        $mimeType = Storage::mimeType($path);

        return response()->stream(function () use ($path) {
            echo Storage::get($path);
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
        ]);
    }
}
