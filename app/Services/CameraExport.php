<?php

namespace App\Services;

use App\Models\Camera;
use App\Models\Category;
use Illuminate\Support\Facades\File;

class CameraExport
{
    public function handle(): void
    {
        $cameras = Camera::with('category')
            ->orderBy('order')
            ->get()
            ->map(fn (Camera $camera) => [
                'id' => $camera->id,
                'name' => $camera->name,
                'stream_url' => $camera->stream_url,
                'adaptive_url' => $camera->adaptive_url,
                'category' => $camera->category->slug ?? '',
                'status' => $camera->status,
            ]);

        $categories = Category::all()
            ->map(fn (Category $category) => [
                'value' => $category->slug,
                'label' => $category->name,
            ]);

        $data = [
            'cameras' => $cameras,
            'categories' => $categories,
        ];

        $path = storage_path('app/public/cameras.json');
        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
