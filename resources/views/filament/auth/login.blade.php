<x-filament::page>
    <div class="flex items-center justify-center min-h-screen bg-[#052e2b]">
        <div class="w-full max-w-md p-8">
            {{-- Logo --}}
            <div class="flex justify-center mb-8">
                <img src="{{ asset('favicon.svg') }}" alt="PATROLI" class="h-16 w-16" />
            </div>

            {{-- Title --}}
            <h1 class="text-2xl font-bold text-center text-white mb-2">
                PATROLI
            </h1>
            <p class="text-center text-gray-400 mb-8">
                CCTV Monitoring Dashboard
            </p>

            {{-- Filament Login Form --}}
            {{ $slot }}
        </div>
    </div>
</x-filament::page>
