<?php

namespace App\Console\Commands;

use App\Models\Camera;
use App\Services\CameraExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CameraCheckStatusCommand extends Command
{
    protected $signature = 'cameras:check-status';

    protected $description = 'Probe stream URLs, determine target URL, and update camera status';

    public function handle(): int
    {
        $cameras = Camera::all();
        $changed = 0;

        foreach ($cameras as $camera) {
            $streamOnline = $this->probeUrl($camera->stream_url);
            $adaptiveOnline = $camera->adaptive_url && $this->probeUrl($camera->adaptive_url);

            $newStatus = ($streamOnline || $adaptiveOnline) ? 'online' : 'offline';
            $statusChanged = $camera->status !== $newStatus;

            $targetUrl = $adaptiveOnline ? $camera->adaptive_url : $camera->stream_url;
            $targetChanged = $camera->target_url !== $targetUrl;

            if ($statusChanged || $targetChanged) {
                $camera->update([
                    'status' => $newStatus,
                    'target_url' => $targetUrl,
                ]);
                $changed++;
                $this->info("Camera [{$camera->name}]: status={$camera->status}->{$newStatus}, target_url updated");
            }
        }

        if ($changed > 0) {
            app(CameraExport::class)->handle();
        }

        $this->info("Checked {$cameras->count()} cameras, {$changed} changes.");

        return Command::SUCCESS;
    }

    private function probeUrl(string $url): bool
    {
        try {
            $response = Http::timeout(5)->get($url);
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
