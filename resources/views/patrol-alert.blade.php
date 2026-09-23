<div class="space-y-3">
    @if($offlineCameras->isNotEmpty())
        <div class="flex items-center justify-between">
            <h4 class="text-sm font-semibold text-red-700 dark:text-red-400">
                ⚠️ {{ $offlineCameras->count() }} Offline Camera(s)
            </h4>
            <span class="inline-flex items-center rounded-full bg-red-100 dark:bg-red-900/30 px-2 py-0.5 text-xs font-medium text-red-800 dark:text-red-300">
                Requires Attention
            </span>
        </div>
        <div class="space-y-2">
            @foreach($offlineCameras as $camera)
                <div class="flex items-center justify-between rounded-lg bg-red-50 dark:bg-red-900/10 px-3 py-2 border border-red-200 dark:border-red-800">
                    <div>
                        <div class="text-sm font-medium text-red-800 dark:text-red-200">{{ $camera->name }}</div>
                        <div class="text-xs text-red-600 dark:text-red-400">
                            {{ $camera->category?->name }}
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
