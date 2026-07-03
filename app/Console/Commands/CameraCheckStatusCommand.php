<?php

namespace App\Console\Commands;

use App\Models\Camera;
use App\Services\CameraExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;

class CameraCheckStatusCommand extends Command
{
    protected $signature = 'cameras:check-status {--only= : Check only specific camera IDs (comma-separated)}';

    protected $description = 'Probe stream URLs, determine target URL, and update camera status';

    public function handle(): int
    {
        $query = Camera::query();

        if ($only = $this->option('only')) {
            $ids = array_map('intval', explode(',', $only));
            $query->whereIn('id', $ids);
        }

        $cameras = $query->get();

        if ($cameras->isEmpty()) {
            $this->warn('No cameras found.');
            return Command::SUCCESS;
        }

        $requests = [];
        foreach ($cameras as $camera) {
            $requests[] = ['camera' => $camera, 'type' => 'stream'];
            if ($camera->adaptive_url) {
                $requests[] = ['camera' => $camera, 'type' => 'adaptive'];
            }
        }

        $responses = Http::pool(function (Pool $pool) use ($requests) {
            foreach ($requests as $req) {
                $url = $req['type'] === 'adaptive'
                    ? $req['camera']->adaptive_url
                    : $req['camera']->stream_url;
                $pool->timeout(5)->get($url);
            }
        });

        $changed = 0;
        $responseIndex = 0;
        foreach ($cameras as $camera) {
            $streamResponse = $responses[$responseIndex++] ?? null;
            $streamOnline = $streamResponse instanceof Response && $streamResponse->successful();

            $adaptiveOnline = false;
            if ($camera->adaptive_url) {
                $adaptiveResponse = $responses[$responseIndex++] ?? null;
                $adaptiveOnline = $adaptiveResponse instanceof Response && $adaptiveResponse->successful();
            }

            $oldStatus = $camera->status;
            $newStatus = ($streamOnline || $adaptiveOnline) ? 'online' : 'offline';
            $statusChanged = $oldStatus !== $newStatus;

            $targetUrl = $adaptiveOnline ? $camera->adaptive_url : $camera->stream_url;
            $targetChanged = $camera->target_url !== $targetUrl;

            if ($statusChanged || $targetChanged) {
                $camera->update([
                    'status' => $newStatus,
                    'target_url' => $targetUrl,
                ]);
                $changed++;
                $this->info("Camera [{$camera->name}]: status={$oldStatus}->{$newStatus}, target_url updated");
            }
        }

        if ($changed > 0) {
            app(CameraExport::class)->handle();
        }

        $this->info("Checked {$cameras->count()} cameras, {$changed} changes.");

        return Command::SUCCESS;
    }
}
