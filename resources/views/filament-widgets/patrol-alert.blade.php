<div class="space-y-4">
    {{-- Summary Stats --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="rounded-lg bg-green-50 dark:bg-green-900/20 p-3 text-center">
            <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $patrolOnline }}</div>
            <div class="text-xs text-green-700 dark:text-green-300">Online</div>
        </div>
        <div class="rounded-lg bg-red-50 dark:bg-red-900/20 p-3 text-center">
            <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $patrolOffline }}</div>
            <div class="text-xs text-red-700 dark:text-red-300">Offline</div>
        </div>
        <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 p-3 text-center">
            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $totalPatrol }}</div>
            <div class="text-xs text-blue-700 dark:text-blue-300">Total</div>
        </div>
    </div>

    {{-- Last Patrol Check --}}
    @if($lastPatrol)
        <div class="rounded-lg bg-gray-50 dark:bg-gray-800/50 p-3">
            <div class="text-xs text-gray-500 dark:text-gray-400">
                Last patrol check: {{ $lastPatrol->checked_at->diffForHumans() }}
                ({{ $lastPatrol->online_count }} online, {{ $lastPatrol->offline_count }} offline)
            </div>
        </div>
    @endif

    {{-- Offline Camera Alerts --}}
    @if($offlineCameras->isNotEmpty())
        <div class="space-y-2">
            <h4 class="text-sm font-semibold text-red-700 dark:text-red-400">
                ⚠️ Offline Cameras ({{ $offlineCameras->count() }})
            </h4>
            @foreach($offlineCameras as $camera)
                <div class="flex items-center justify-between rounded-lg bg-red-50 dark:bg-red-900/10 px-3 py-2 border border-red-200 dark:border-red-800">
                    <div>
                        <div class="text-sm font-medium text-red-800 dark:text-red-200">{{ $camera->name }}</div>
                        <div class="text-xs text-red-600 dark:text-red-400">
                            {{ $camera->category?->name }} • {{ $camera->stream_url }}
                        </div>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-red-100 dark:bg-red-900/30 px-2 py-0.5 text-xs font-medium text-red-800 dark:text-red-300">
                        Offline
                    </span>
                </div>
            @endforeach
        </div>
    @else
        <div class="rounded-lg bg-green-50 dark:bg-green-900/20 p-3 text-center">
            <div class="text-sm text-green-700 dark:text-green-400">
                ✅ All patrol cameras are online
            </div>
        </div>
    @endif
</div>
