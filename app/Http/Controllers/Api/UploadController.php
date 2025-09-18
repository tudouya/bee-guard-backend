<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Uploads\UploadStoreRequest;
use App\Models\Upload;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function store(UploadStoreRequest $request): JsonResponse
    {
        $user = $request->user();
        $file = $request->file('file');

        $disk = 'public';
        $scene = (string) ($request->input('scene') ?? '');
        $baseDir = ($scene === 'avatar') ? 'avatars' : 'payment-proofs';
        $path = $file->store($baseDir.'/'.date('Ymd'), $disk);
        $upload = Upload::query()->create([
            'user_id' => $user?->id,
            'disk' => $disk,
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'id' => $upload->id,
                'url' => Storage::disk($disk)->url($path),
                'path' => $path, // relative storage path, e.g., avatars/20250918/xxx.jpg
            ],
        ]);
    }
}
