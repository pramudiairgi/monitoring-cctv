<?php

namespace App\Console\Commands;

use Throwable;
use App\Models\Camera;
use App\Rules\PublicHttpUrl;
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

        // Per-run DNS cache: host (lowercased) => resolves. Collapses one
        // lookup per URL into one lookup per host for the whole run.
        $dnsCache = [];
        $hostResolves = function (string $host) use (&$dnsCache): bool {
            $key = strtolower($host);
            if (! array_key_exists($key, $dnsCache)) {
                if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
                    $dnsCache[$key] = true;
                } else {
                    $dnsCache[$key] = (gethostbynamel($host) ?: []) !== []
                        || (dns_get_record($host, DNS_AAAA) ?: []) !== [];
                }
            }

            return $dnsCache[$key];
        };

        // Per-run SSRF verdict cache: scheme://host => allowed. URLs sharing
        // a host reuse the verdict instead of re-resolving DNS each time.
        $allowCache = [];
        $isAllowedCached = function (?string $url) use (&$allowCache): bool {
            if (! is_string($url) || $url === '') {
                return false;
            }
            $parts = parse_url($url);
            if ($parts === false) {
                return false;
            }
            $scheme = strtolower($parts['scheme'] ?? '');
            $host = strtolower($parts['host'] ?? '');
            if ($scheme === '' || $host === '') {
                return false;
            }
            $key = $scheme.'://'.$host;
            if (! array_key_exists($key, $allowCache)) {
                $allowCache[$key] = PublicHttpUrl::isAllowed($url);
            }

            return $allowCache[$key];
        };

        $safeRequests = [];
        $skippedIds = [];
        foreach ($cameras as $camera) {
            // DNS resolution failure is transient: retain the previous status
            // instead of flipping the camera offline.
            $unresolved = false;
            foreach (array_filter([$camera->stream_url, $camera->adaptive_url]) as $url) {
                $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;
                if (is_string($host) && $host !== ''
                    && filter_var($host, FILTER_VALIDATE_IP) === false
                    && ! $hostResolves($host)) {
                    $unresolved = true;
                    break;
                }
            }
            if ($unresolved) {
                $this->warn("Camera [{$camera->name}]: DNS resolution failed, retaining status ({$camera->status}).");
                $skippedIds[$camera->id] = true;
                continue;
            }

            if ($isAllowedCached($camera->stream_url)) {
                $safeRequests[] = ['camera' => $camera, 'type' => 'stream', 'url' => $camera->stream_url];
            } else {
                $this->warn("Camera [{$camera->name}]: skipping unsafe stream_url, marking offline.");
            }
            if ($camera->adaptive_url) {
                if ($isAllowedCached($camera->adaptive_url)) {
                    $safeRequests[] = ['camera' => $camera, 'type' => 'adaptive', 'url' => $camera->adaptive_url];
                } else {
                    $this->warn("Camera [{$camera->name}]: skipping unsafe adaptive_url.");
                }
            }
        }

        $responses = [];
        try {
            $responses = Http::pool(function (Pool $pool) use ($safeRequests) {
                foreach ($safeRequests as $req) {
                    $pool->withoutRedirecting()->timeout(5)->get($req['url']);
                }
            });
        } catch (Throwable $e) {
            $this->error('HTTP pool request failed: ' . $e->getMessage());
        }

        // Map pooled responses back to camera+type in request order.
        // URLs that failed the SSRF guard were never fetched and stay offline.
        $onlineByKey = [];
        foreach ($safeRequests as $index => $req) {
            $response = $responses[$index] ?? null;
            $onlineByKey[$req['camera']->id . ':' . $req['type']] =
                $response instanceof Response && $response->successful();
        }

        $changed = 0;
        foreach ($cameras as $camera) {
            if (isset($skippedIds[$camera->id])) {
                continue;
            }

            $streamOnline = $onlineByKey[$camera->id . ':stream'] ?? false;
            $adaptiveOnline = false;
            if ($camera->adaptive_url) {
                $adaptiveOnline = $onlineByKey[$camera->id . ':adaptive'] ?? false;
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
