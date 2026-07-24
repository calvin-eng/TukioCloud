<?php

use App\Models\Event;
use App\Models\Guest;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $selectedEventId = '';

    public function mount(): void
    {
        $events = Event::where('tenant_id', session('tenant_id'))->get();
        if ($events->count() === 1) {
            $this->selectedEventId = (string) $events->first()->id;
        }
    }

    public function updatedSelectedEventId(string $value): void
    {
        if (!$value) return;

        $tenantId = session('tenant_id');
        $event = Event::where('tenant_id', $tenantId)->find((int) $value);
        if (!$event) return;

        $guests = Guest::query()
            ->select('guests.id', 'guests.name', 'guests.qr_token', 'guests.short_code', 'guests.event_id', 'checkins.checked_in_at as checked_in_at')
            ->leftJoin('checkins', 'guests.id', '=', 'checkins.guest_id')
            ->where('guests.event_id', $event->id)
            ->where('guests.tenant_id', $tenantId)
            ->get();

        $this->dispatch('guests-loaded', eventId: $event->id, guests: $guests->toArray());
    }

    public function render(): mixed
    {
        $tenantId = session('tenant_id');
        $events = Event::where('tenant_id', $tenantId)->get();
        $selectedEvent = null;
        $guests = collect();

        if ($this->selectedEventId) {
            $selectedEvent = $events->firstWhere('id', (int) $this->selectedEventId);
        } else {
            $selectedEvent = $events->first();
        }

        if ($selectedEvent) {
            $guests = Guest::query()
                ->select('guests.id', 'guests.name', 'guests.qr_token', 'guests.short_code', 'guests.event_id', 'checkins.checked_in_at as checked_in_at')
                ->leftJoin('checkins', 'guests.id', '=', 'checkins.guest_id')
                ->where('guests.event_id', $selectedEvent->id)
                ->where('guests.tenant_id', $tenantId)
                ->get();
        }

        return view('livewire.pages.checkin.index', [
            'events' => $events,
            'selectedEvent' => $selectedEvent,
            'guests' => $guests,
        ]);
    }
};

?>

<div>
    @if ($selectedEvent)
    <div class="py-12" x-data="checkinApp()" x-init="init()">
        <div class="mx-auto px-4 sm:px-6 lg:px-8 max-w-2xl">
            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Check-In</h1>
                @if ($events->count() > 1)
                <div class="mt-3">
                    <select wire:model.live="selectedEventId"
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        @foreach ($events as $ev)
                            <option value="{{ $ev->id }}">{{ $ev->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">{{ $selectedEvent->name }}</p>
            </div>

            <!-- Mode Toggle -->
            <div class="flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden mb-6">
                <button @click="mode = 'camera'; $nextTick(() => startScanner())"
                        :class="mode === 'camera' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300'"
                        class="flex-1 px-4 py-2.5 text-sm font-medium transition-colors">
                    Scan QR
                </button>
                <button @click="mode = 'manual'; stopScanner()"
                        :class="mode === 'manual' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300'"
                        class="flex-1 px-4 py-2.5 text-sm font-medium transition-colors border-l border-gray-300 dark:border-gray-600">
                    Enter Code
                </button>
            </div>

            <!-- Camera Scanner -->
            <div x-show="mode === 'camera'" class="mb-6">
                <div id="qr-reader" class="w-full max-w-sm mx-auto rounded-lg overflow-hidden"></div>
                
                <!-- Camera Error Banner -->
                <div x-show="cameraError" style="display:none" class="mt-3 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-sm text-red-600 dark:text-red-400 flex items-center justify-between gap-2">
                    <span x-text="cameraError"></span>
                    <button x-show="!scannerRunning && cameraError" @click="startScanner()" class="px-2.5 py-1 bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 rounded font-medium hover:bg-red-200 transition-colors shrink-0">Try again</button>
                </div>

                <div x-show="!scannerRunning && !cameraError" class="mt-3 text-center text-sm text-gray-500 dark:text-gray-400">
                    <button @click="startScanner()" class="text-indigo-600 dark:text-indigo-400 hover:underline">Start camera</button>
                </div>
            </div>

            <!-- Manual Entry -->
            <div x-show="mode === 'manual'" class="mb-6">
                <div class="flex gap-2">
                    <input type="text"
                           x-model="manualCode"
                           @keyup.enter="processManualCode()"
                           placeholder="Enter guest code..."
                           class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <button @click="processManualCode()"
                            :disabled="!manualCode.trim()"
                            class="px-6 py-2 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                        Check In
                    </button>
                </div>
            </div>

            <!-- Result Banner -->
            <div x-show="result && result.show" x-transition class="mb-6 rounded-lg p-4"
                 :class="result && {
                     'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800': result.status === 'checked_in',
                     'bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800': result.status === 'already_checked_in',
                     'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800': result.status === 'invalid'
                 } || ''">
                <div class="flex items-center gap-3">
                    <div class="text-2xl">
                        <template x-if="result && result.status === 'checked_in'">&#10003;</template>
                        <template x-if="result && result.status === 'already_checked_in'">&#9888;</template>
                        <template x-if="result && result.status === 'invalid'">&#10007;</template>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-lg"
                           :class="result && {
                               'text-green-800 dark:text-green-200': result.status === 'checked_in',
                               'text-amber-800 dark:text-amber-200': result.status === 'already_checked_in',
                               'text-red-800 dark:text-red-200': result.status === 'invalid'
                           } || ''"
                           x-text="result && result.name"></p>
                        <p class="text-sm mt-0.5"
                           :class="result && {
                               'text-green-600 dark:text-green-400': result.status === 'checked_in',
                               'text-amber-600 dark:text-green-400': result.status === 'already_checked_in',
                               'text-red-600 dark:text-red-400': result.status === 'invalid'
                           } || ''"
                           x-text="result && result.message"></p>
                    </div>
                    <button @click="result && (result.show = false)" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 shrink-0">
                        <span class="text-xl leading-none">&times;</span>
                    </button>
                </div>
            </div>

            <!-- Network Status -->
            <div x-show="!online" class="mb-4 text-center">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                    Offline — check-ins will sync when connection returns
                </span>
            </div>

            <!-- Recent Check-ins -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Recent Check-ins
                        <span x-show="pendingCount > 0" class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                            <span x-text="pendingCount"></span> pending sync
                        </span>
                    </h2>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700 max-h-80 overflow-y-auto">
                    <template x-for="checkin in recentCheckins" :key="checkin.guest_token">
                        <div class="px-4 py-3 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="checkin.name"></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400" x-text="formatTime(checkin.checked_in_at)"></p>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                  :class="checkin.synced
                                      ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                      : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'"
                                  x-text="checkin.synced ? 'Synced' : 'Syncing...'">
                            </span>
                        </div>
                    </template>
                    <div x-show="recentCheckins.length === 0" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                        No check-ins yet.
                    </div>
                </div>
            </div>
        </div>
    </div>

    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/checkin.js'])

    <script>
        window.TukioCheckinReady = new Promise(function (resolve) {
            window._resolveTukioCheckinReady = resolve;
        });
        window.__CHECKIN_DATA = {
            eventId: {{ $selectedEvent->id }},
            guests: @json($guests)
        };
    </script>
    @else
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <div class="py-12">
        <div class="mx-auto px-4 sm:px-6 lg:px-8 max-w-2xl">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    No active event found. Please contact an administrator.
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
