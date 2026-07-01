<?php

namespace App\Services;

use App\Models\Camera;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class CameraExport
{
    public function handle(): void
    {
        $proxyPrefix = config('services.hls.proxy_prefix');
        $proxyDomains = config('services.hls.proxy_domains', []);

        $cameras = Camera::with('category')
            ->orderBy('order')
            ->get()
            ->map(fn (Camera $camera) => [
                'id' => $camera->id,
                'name' => $camera->name,
                'stream_url' => $this->proxyUrl($camera->stream_url, $proxyPrefix, $proxyDomains),
                'adaptive_url' => $camera->adaptive_url
                    ? $this->proxyUrl($camera->adaptive_url, $proxyPrefix, $proxyDomains)
                    : null,
                'target_url' => $camera->target_url
                    ? $this->proxyUrl($camera->target_url, $proxyPrefix, $proxyDomains)
                    : $this->proxyUrl($camera->stream_url, $proxyPrefix, $proxyDomains),
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
        Cache::forget('cameras_json');
    }

    private function proxyUrl(string $url, ?string $prefix, array $domains): string
    {
        if (!$prefix || !$domains) {
            return $url;
        }
        foreach ($domains as $domain) {
            $url = str_replace($domain, $prefix, $url);
        }
        return $url;
    }
}
