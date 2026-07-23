<?php

use App\Models\Template;
use App\Services\CardGenerationService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public ?int $templateId = null;

    public string $name = '';

    public $background = null;

    public string $backgroundPreview = '';

    public int $nameX = 540;
    public int $nameY = 600;
    public int $qrX = 390;
    public int $qrY = 950;

    public string $previewUri = '';

    public bool $saved = false;

    public function mount(): void
    {
        if ($this->templateId) {
            $t = Template::findOrFail($this->templateId);
            $this->name = $t->name;
            $this->nameX = $t->name_x;
            $this->nameY = $t->name_y;
            $this->qrX = $t->qr_x;
            $this->qrY = $t->qr_y;
            if ($t->background_path && Storage::disk('public')->exists($t->background_path)) {
                $this->backgroundPreview = route('calibrate.bg', ['filename' => basename($t->background_path)]);
                session(['calibrate_bg' => $t->background_path]);
            }
            $this->refreshPreview();
        }
    }

    public function updatedBackground(): void
    {
        $this->validate(['background' => 'image|max:5120']);
        $this->storeBackground();
        $bg = session('calibrate_bg');
        $this->backgroundPreview = $bg ? route('calibrate.bg', ['filename' => basename($bg)]) : '';
        $this->refreshPreview();
    }

    public function setMarker(string $type, int $xPct, int $yPct): void
    {
        if ($type === 'name') {
            $this->nameX = intval(round($xPct / 100 * 1080));
            $this->nameY = intval(round($yPct / 100 * 1350));
        } else {
            $this->qrX = intval(round($xPct / 100 * 1080));
            $this->qrY = intval(round($yPct / 100 * 1350));
        }
        $this->refreshPreview();
    }

    public function refreshPreview(): void
    {
        $template = $this->resolveTemplate();
        if (!$template) {
            $this->previewUri = '';
            return;
        }
        try {
            session([
                'preview_name_x' => $template->name_x,
                'preview_name_y' => $template->name_y,
                'preview_qr_x' => $template->qr_x,
                'preview_qr_y' => $template->qr_y,
                'preview_qr_size' => $template->qr_size ?? 300,
                'preview_name_font_color' => $template->name_font_color ?? '#1a1a1a',
                'preview_name_font_size' => $template->name_font_size ?? 48,
                'preview_name_font_path' => $template->name_font_path ?? resource_path('fonts/NotoSans-Regular.ttf'),
                'preview_output_quality' => $template->output_quality ?? 90,
                'preview_output_width' => $template->output_width ?? 1080,
                'preview_output_height' => $template->output_height ?? 1350,
            ]);
            $this->previewUri = route('calibrate.preview', ['t' => time()]);
        } catch (\Exception $e) {
            $this->previewUri = '';
        }
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255|unique:templates,name,' . ($this->templateId ?? 'NULL'),
            'background' => 'nullable|image|max:5120',
        ]);

        $this->storeBackground();
        $bg = session('calibrate_bg');

        $data = [
            'name' => $this->name,
            'name_x' => $this->nameX,
            'name_y' => $this->nameY,
            'qr_x' => $this->qrX,
            'qr_y' => $this->qrY,
            'output_width' => 1080,
            'output_height' => 1350,
        ];

        if ($bg) {
            $data['background_path'] = $bg;
        }

        if ($this->templateId) {
            Template::findOrFail($this->templateId)->update($data);
        } else {
            Template::create($data);
        }

        $this->saved = true;
    }

    private function storeBackground(): void
    {
        if (!$this->background) {
            return;
        }
        $path = $this->background->store('templates', 'public');
        session(['calibrate_bg' => $path]);
    }

    private function resolveTemplate(): ?Template
    {
        if ($this->templateId) {
            $template = Template::find($this->templateId);
            if (!$template) return null;
        } else {
            $template = new Template;
            $template->name = $this->name ?: 'preview';
            $template->qr_size = 300;
            $template->name_font_color = '#1a1a1a';
            $template->name_font_size = 48;
            $template->name_font_path = resource_path('fonts/NotoSans-Regular.ttf');
            $template->output_quality = 90;
            $template->output_width = 1080;
            $template->output_height = 1350;
        }

        $template->name_x = $this->nameX;
        $template->name_y = $this->nameY;
        $template->qr_x = $this->qrX;
        $template->qr_y = $this->qrY;

        $bg = session('calibrate_bg');
        if (!$bg && $this->templateId) {
            $bg = $template->background_path;
        }
        if ($bg) {
            $template->background_path = $bg;
        }

        return $template;
    }
}; ?>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    {{ $templateId ? 'Edit Template' : 'New Template' }}
                </h2>
            </div>

            <div class="space-y-6">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="name" value="Template Name" />
                        <x-text-input id="name" wire:model.live="name" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="background" value="Background Image (max 5 MB)" />
                        <input id="background" type="file" wire:model="background" accept="image/*"
                            class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-900 dark:file:text-indigo-300" />
                        <x-input-error :messages="$errors->get('background')" class="mt-1" />
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Calibration</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                            Drag the <span class="font-semibold text-blue-600">name</span> and <span class="font-semibold text-green-600">QR</span> markers to position them on the card.
                        </p>

                        @if ($backgroundPreview)
                            <div class="relative border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg overflow-hidden select-none"
                                x-data="{
                                    dragging: null,
                                    localNameX: {{ $nameX }},
                                    localNameY: {{ $nameY }},
                                    localQrX: {{ $qrX }},
                                    localQrY: {{ $qrY }},
                                    previewTimer: null,
                                }"
                                @mousedown.window="
                                    const el = $event.target.closest('[data-marker]');
                                    if (!el) return;
                                    window.__calibrateMarker = el.dataset.marker;
                                    window.__calibrateDragging = true;
                                    dragging = el.dataset.marker;
                                    $event.preventDefault();
                                "
                                @mousemove.window="
                                    if (!window.__calibrateDragging) return;
                                    const type = window.__calibrateMarker;
                                    const calImg = document.getElementById('cal-img');
                                    if (!calImg) return;
                                    const r = calImg.getBoundingClientRect();
                                    const x = Math.round(Math.max(0, Math.min(1, ($event.clientX - r.left) / r.width)) * 1080);
                                    const y = Math.round(Math.max(0, Math.min(1, ($event.clientY - r.top) / r.height)) * 1350);
                                    if (type === 'name') { localNameX = x; localNameY = y; }
                                    else { localQrX = x; localQrY = y; }
                                    if (previewTimer) clearTimeout(previewTimer);
                                    previewTimer = setTimeout(() => {
                                        if (!window.__calibrateDragging) return;
                                        const t = window.__calibrateMarker;
                                        const px = t === 'name' ? localNameX : localQrX;
                                        const py = t === 'name' ? localNameY : localQrY;
                                        const xPct = Math.round(px / 1080 * 10000) / 100;
                                        const yPct = Math.round(py / 1350 * 10000) / 100;
                                        console.log('[Calibrate] preview', t, xPct, yPct);
                                        $wire.setMarker(t, xPct, yPct);
                                    }, 180);
                                "
                                @mouseup.window="
                                    if (!window.__calibrateDragging) return;
                                    window.__calibrateDragging = false;
                                    const type = window.__calibrateMarker;
                                    window.__calibrateMarker = null;
                                    if (previewTimer) clearTimeout(previewTimer);
                                    const px = type === 'name' ? localNameX : localQrX;
                                    const py = type === 'name' ? localNameY : localQrY;
                                    const xPct = Math.round(px / 1080 * 10000) / 100;
                                    const yPct = Math.round(py / 1350 * 10000) / 100;
                                    console.log('[Calibrate] finalize', type, xPct, yPct);
                                    $wire.setMarker(type, xPct, yPct);
                                    dragging = null;
                                ">

                                <img id="cal-img" src="{{ $backgroundPreview }}" class="w-full rounded-lg" alt="Card background" draggable="false" />

                                <div data-marker="name"
                                    class="absolute z-10 flex items-center gap-1.5 cursor-grab active:cursor-grabbing select-none"
                                    :style="`left: ${localNameX / 1080 * 100}%; top: ${localNameY / 1350 * 100}%`">
                                    <span class="w-6 h-6 rounded-full bg-blue-600 border-2 border-blue-300 flex items-center justify-center text-xs font-bold text-white shadow-md">N</span>
                                    <span class="text-xs font-mono bg-gray-900/75 text-white px-1.5 py-0.5 rounded whitespace-nowrap" x-text="`${localNameX},${localNameY}`"></span>
                                </div>

                                <div data-marker="qr"
                                    class="absolute z-10 flex items-center gap-1.5 cursor-grab active:cursor-grabbing select-none"
                                    :style="`left: ${localQrX / 1080 * 100}%; top: ${localQrY / 1350 * 100}%`">
                                    <span class="w-6 h-6 rounded-full bg-green-600 border-2 border-green-300 flex items-center justify-center text-xs font-bold text-white shadow-md">Q</span>
                                    <span class="text-xs font-mono bg-gray-900/75 text-white px-1.5 py-0.5 rounded whitespace-nowrap" x-text="`${localQrX},${localQrY}`"></span>
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                                <div>
                                    <x-input-label value="Name X" />
                                    <x-text-input wire:model.live="nameX" type="number" class="mt-1 block w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Name Y" />
                                    <x-text-input wire:model.live="nameY" type="number" class="mt-1 block w-full" />
                                </div>
                                <div>
                                    <x-input-label value="QR X" />
                                    <x-text-input wire:model.live="qrX" type="number" class="mt-1 block w-full" />
                                </div>
                                <div>
                                    <x-input-label value="QR Y" />
                                    <x-text-input wire:model.live="qrY" type="number" class="mt-1 block w-full" />
                                </div>
                            </div>
                        @else
                            <div class="flex items-center justify-center h-80 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-gray-400 text-sm">
                                Upload a background image to begin
                            </div>
                        @endif
                    </div>

                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Live Preview</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Preview updates live while dragging markers.</p>
                        @if ($previewUri)
                            <img src="{{ $previewUri }}" class="w-full rounded-lg shadow" alt="Preview" />
                        @else
                            <div class="flex items-center justify-center h-80 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-gray-400 text-sm">
                                Adjust markers to see preview
                            </div>
                        @endif
                    </div>
                </div>

                <div class="flex items-center gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <x-primary-button wire:click="save" wire:loading.attr="disabled">
                        {{ $templateId ? 'Update' : 'Save' }} Template
                    </x-primary-button>

                    @if ($saved)
                        <span class="text-sm text-green-600 dark:text-green-400 font-medium">Saved successfully!</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
