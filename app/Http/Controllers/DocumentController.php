<?php

namespace App\Http\Controllers;
use App\Models\Chunk;
use App\Models\File;
use App\Services\ChunkerService;
use DB;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;

use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function chunk(Request $request)
    {

        // Validate the request
        $validated = $request->validate([
            "url" => "required|string",
        ]);

//        // Check if the file already exists
//        $file = File::where('url', $validated['url'])->first();

        // Create a File
        $file = new File(['url' => $validated['url']]);

        $content = file_get_contents($file->url);
        $file->sha256 = hash('sha256', $content);
        $file->save();

        $chunk_arrays = ChunkerService::chunkMarkdown($content, 1000);
        $chunks = ChunkerService::parsePageNumbers($chunk_arrays);

        foreach ($chunks as $key => $chunk_array)
        {
            Chunk::create([
                'text' => $chunk_array['text'],
                'type' => 'paragraph',
                'chunk_number' => $key,
                'page_number' => $chunk_array['page_number'],
                'file_id' => $file->id,
            ]);
        }
    }


    
}
