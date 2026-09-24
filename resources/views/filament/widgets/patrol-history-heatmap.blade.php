@php
    $levelClasses = [
        0 => 'bg-gray-100 dark:bg-gray-800',
        1 => 'bg-emerald-200 dark:bg-emerald-900',
        2 => 'bg-emerald-300 dark:bg-emerald-700',
        3 => 'bg-emerald-500 dark:bg-emerald-500',
        4 => 'bg-emerald-600 dark:bg-emerald-400',
    ];

    $dayLabels = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
@endphp

<x-filament-widgets::widget class="fi-wi-patrol-history">
    <x-filament::section :heading="$heading" :description="$description">
        <div wire:poll.60s class="overflow-x-auto">
            @if ($hasData)
                <div
                    class="inline-grid gap-1"
                    style="grid-template-columns: auto repeat({{ count($weeks) }}, 14px); grid-template-rows: 14px repeat(7, 14px);"
                >
                    {{-- Corner --}}
                    <div></div>

                    {{-- Month labels --}}
                    @foreach ($weeks as $week)
                        <div class="flex h-3.5 items-center text-[10px] text-gray-400 dark:text-gray-500">
                            {{ $week['monthLabel'] ?? '' }}
                        </div>
                    @endforeach

                    {{-- Day labels + cells --}}
                    @foreach ($dayLabels as $row => $dayLabel)
                        <div class="flex h-3.5 items-center pr-1 text-[10px] text-gray-400 dark:text-gray-500">
                            {{ $dayLabel }}
                        </div>

                        @foreach ($weeks as $week)
                            @php
                                $day = $week['days'][$row];
                            @endphp
                            <div
                                class="h-3.5 w-3.5 rounded-[3px] {{ $levelClasses[$day['level']] }}"
                                title="{{ $day['date']->format('d M Y') }} — {{ $day['count'] }} pengecekan patroli online"
                            ></div>
                        @endforeach
                    @endforeach
                </div>

                {{-- Legend --}}
                <div class="mt-3 flex items-center gap-1.5 text-[10px] text-gray-400 dark:text-gray-500">
                    <span>Sedikit</span>
                    @foreach ([0, 1, 2, 3, 4] as $level)
                        <div class="h-3 w-3 rounded-[3px] {{ $levelClasses[$level] }}"></div>
                    @endforeach
                    <span>Banyak</span>
                </div>
            @else
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Belum ada data patroli. Data akan muncul setelah kamera patroli terdeteksi online.
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
