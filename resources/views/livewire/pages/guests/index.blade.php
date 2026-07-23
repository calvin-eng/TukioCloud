<?php

use App\Imports\GuestImport;
use App\Models\Event;
use App\Models\Guest;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\Volt\Component;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;
    public bool $showForm = false;

    public ?int $editingGuestId = null;

    public ?int $eventId = null;

    public string $name = '';

    public string $phone = '';

    public bool $hasWhatsapp = false;

    public bool $showImportForm = false;

    public bool $showImportSummary = false;

    #[Rule('required|file|mimes:xls,xlsx,csv|max:10240')]
    public $importFile = null;

    public array $importSummary = [
        'created' => 0,
        'skipped' => 0,
        'entries' => [],
    ];

    public function mount(): void
    {
        $this->eventId = request()->query('event_id')
            ? (int) request()->query('event_id')
            : null;
    }

    public function render(): mixed
    {
        $query = Guest::where('tenant_id', auth()->user()->tenant_id)
            ->with('event');

        if ($this->eventId) {
            $query->where('event_id', $this->eventId);
        }

        return view('livewire.pages.guests.index', [
            'guests' => $query->orderByDesc('created_at')->get(),
            'events' => Event::where('tenant_id', auth()->user()->tenant_id)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function filterByEvent($eventId): void
    {
        $this->eventId = ($eventId === '' || $eventId === null) ? null : (int) $eventId;
    }

    public function clearFilter(): void
    {
        $this->eventId = null;
    }

    public function openCreateForm(): void
    {
        $this->resetForm();
        $this->showForm = true;
        $this->editingGuestId = null;
    }

    public function edit($id): void
    {
        $guest = Guest::where('tenant_id', auth()->user()->tenant_id)->findOrFail((int) $id);
        $this->editingGuestId = $guest->id;
        $this->eventId = $guest->event_id;
        $this->name = $guest->name;
        $this->phone = $guest->phone;
        $this->hasWhatsapp = $guest->has_whatsapp;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'eventId' => 'required|exists:events,id',
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'hasWhatsapp' => 'boolean',
        ]);

        $data = [
            'event_id' => $this->eventId,
            'name' => $this->name,
            'phone' => $this->phone,
            'has_whatsapp' => $this->hasWhatsapp,
        ];

        if ($this->editingGuestId) {
            $guest = Guest::where('tenant_id', auth()->user()->tenant_id)->findOrFail($this->editingGuestId);
            $guest->update($data);
        } else {
            $data['tenant_id'] = auth()->user()->tenant_id;
            $data['qr_token'] = Str::random(32);
            $data['short_code'] = strtoupper(Str::random(8));
            Guest::create($data);
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function delete($id): void
    {
        $guest = Guest::where('tenant_id', auth()->user()->tenant_id)->findOrFail((int) $id);
        $guest->delete();
    }

    public function openImportForm(): void
    {
        $this->resetImport();
        $this->showImportForm = true;
    }

    public function import(): void
    {
        $this->validate([
            'importFile' => 'required|file|mimes:xls,xlsx,csv|max:10240',
            'eventId' => 'required|exists:events,id',
        ]);

        $import = new GuestImport($this->eventId, auth()->user()->tenant_id);
        Excel::import($import, $this->importFile->getRealPath());

        $this->importSummary = [
            'created' => $import->created,
            'skipped' => count($import->skipped),
            'entries' => $import->skipped,
        ];

        $this->showImportForm = false;
        $this->showImportSummary = true;
        $this->importFile = null;
    }

    public function closeImportSummary(): void
    {
        $this->showImportSummary = false;
    }

    private function resetImport(): void
    {
        $this->importFile = null;
        $this->importSummary = ['created' => 0, 'skipped' => 0, 'entries' => []];
    }

    private function resetForm(): void
    {
        $this->name = '';
        $this->phone = '';
        $this->hasWhatsapp = false;
        $this->editingGuestId = null;
    }
}; ?>

<div>
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Guests</h2>
        <div class="flex items-center gap-3">
            <x-secondary-button wire:click="openImportForm">Import from Excel</x-secondary-button>
            <x-primary-button wire:click="openCreateForm">Add Guest</x-primary-button>
        </div>
    </div>

    <div class="mb-4 flex items-center gap-3">
        <select wire:model.live="eventId" wire:change="filterByEvent($event.target.value)"
            class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
            <option value="">All Events</option>
            @foreach($events as $event)
                <option value="{{ $event->id }}">{{ $event->name }}</option>
            @endforeach
        </select>
        @if($eventId)
            <span class="text-sm text-gray-500 dark:text-gray-400">
                filtered by {{ $events->firstWhere('id', $eventId)?->name }}
            </span>
        @endif
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Phone</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">WhatsApp</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Event</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($guests as $guest)
                    <tr>
                        <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $guest->name }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $guest->phone }}</td>
                        <td class="px-6 py-4 text-sm">
                            @if($guest->has_whatsapp)
                                <span class="text-green-600 dark:text-green-400 font-medium">Yes</span>
                            @else
                                <span class="text-gray-400">No</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $guest->event?->name }}</td>
                        <td class="px-6 py-4 text-right text-sm space-x-2">
                            <x-secondary-button wire:click="edit({{ $guest->id }})">Edit</x-secondary-button>
                            <x-danger-button
                                wire:click="delete({{ $guest->id }})"
                                wire:confirm="Remove this guest?"
                            >Delete</x-danger-button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                            @if($eventId)
                                No guests for this event yet. Click "Add Guest" or "Import from Excel" to add guests.
                            @else
                                No guests yet. Click "Add Guest" or "Import from Excel" to add guests.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Import Form Modal --}}
    @if($showImportForm)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0" wire:key="import-form-modal">
            <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75" wire:click="$set('showImportForm', false)"></div>
            <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg shadow-xl overflow-hidden sm:mx-auto sm:max-w-lg relative z-10">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Import Guests from Excel</h3>

                    <div class="space-y-4">
                        <div>
                            <x-input-label for="import-event" value="Event" />
                            <select id="import-event"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                wire:model="eventId">
                                <option value="">Select Event</option>
                                @foreach($events as $event)
                                    <option value="{{ $event->id }}">{{ $event->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('eventId')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="import-file" value="File (.xlsx or .csv)" />
                            <input id="import-file" type="file" accept=".xlsx,.csv"
                                class="mt-1 block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 dark:file:bg-indigo-900 file:text-indigo-700 dark:file:text-indigo-300 hover:file:bg-indigo-100 dark:hover:file:bg-indigo-800"
                                wire:model="importFile">
                            <x-input-error :messages="$errors->get('importFile')" class="mt-2" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                The first row must contain headers. Columns named "Name" and "Phone" are auto-detected. An optional "Notes" column is also supported.
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <x-secondary-button type="button" wire:click="$set('showImportForm', false)">Cancel</x-secondary-button>
                        <x-primary-button wire:click="import">Import</x-primary-button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Import Summary Modal --}}
    @if($showImportSummary)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0" wire:key="import-summary-modal">
            <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75" wire:click="closeImportSummary"></div>
            <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg shadow-xl overflow-hidden sm:mx-auto sm:max-w-lg relative z-10">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Import Complete</h3>

                    <div class="space-y-3">
                        <div class="flex items-center gap-2 text-sm">
                            <span class="text-green-600 dark:text-green-400 font-semibold text-lg">{{ $importSummary['created'] }}</span>
                            <span class="text-gray-700 dark:text-gray-300">guests created</span>
                        </div>

                        @if($importSummary['skipped'] > 0)
                            <div class="flex items-center gap-2 text-sm">
                                <span class="text-amber-600 dark:text-amber-400 font-semibold text-lg">{{ $importSummary['skipped'] }}</span>
                                <span class="text-gray-700 dark:text-gray-300">skipped</span>
                            </div>

                            <div class="mt-3 max-h-48 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-md divide-y divide-gray-200 dark:divide-gray-600">
                                @foreach($importSummary['entries'] as $entry)
                                    <div class="px-3 py-2 text-xs text-gray-600 dark:text-gray-400">
                                        <span class="font-medium">{{ $entry['name'] }}</span>
                                        <span class="text-gray-400">({{ $entry['phone'] }})</span>
                                        &mdash; {{ $entry['reason'] }}
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="mt-6 flex justify-end">
                        <x-primary-button wire:click="closeImportSummary">Done</x-primary-button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Create/Edit Guest Modal --}}
    @if($showForm)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0" wire:key="guest-form-modal">
            <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75" wire:click="$set('showForm', false)"></div>
            <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg shadow-xl overflow-hidden sm:mx-auto sm:max-w-lg relative z-10">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                        {{ $editingGuestId ? 'Edit Guest' : 'Add Guest' }}
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <x-input-label for="guest-event" value="Event" />
                            <select id="guest-event"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                wire:model="eventId">
                                <option value="">Select Event</option>
                                @foreach($events as $event)
                                    <option value="{{ $event->id }}">{{ $event->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('eventId')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="guest-name" value="Name" />
                            <x-text-input id="guest-name" class="block mt-1 w-full" wire:model="name" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="guest-phone" value="Phone" />
                            <x-text-input id="guest-phone" type="tel" class="block mt-1 w-full" wire:model="phone" />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>
                        <div>
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" wire:model="hasWhatsapp"
                                    class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                Has WhatsApp
                            </label>
                            <x-input-error :messages="$errors->get('hasWhatsapp')" class="mt-2" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <x-secondary-button type="button" wire:click="$set('showForm', false)">Cancel</x-secondary-button>
                        <x-primary-button wire:click="save">Save</x-primary-button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
