<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Guest;
use App\Models\Template;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Alignment;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\Font;

class CardGenerationService
{
    public function __construct(
        private ImageManager $image,
        private string $template = 'default',
    ) {}

    public function generate(Guest $guest): string
    {
        $dbTemplate = $this->resolveTemplate($guest);

        if ($dbTemplate) {
            return $this->generateFromTemplate($guest, $dbTemplate);
        }

        $template = config("templates.$this->template", config('templates.default'));
        $output = $template['output'];

        $canvas = $this->buildCanvas($template);
        $this->placeQrCode($canvas, $guest->qr_token, $template['qr_code']);
        $this->placeName($canvas, $guest->name, $template['name']);

        $path = sprintf(
            'cards/%s/%s.jpg',
            $guest->event_id,
            $guest->short_code,
        );

        Storage::disk('local')->makeDirectory(dirname($path));

        $canvas->save(
            Storage::disk('local')->path($path),
            quality: $output['quality'],
        );

        return Storage::disk('local')->path($path);
    }

    private function resolveTemplate(Guest $guest): ?Template
    {
        $event = $guest->event;

        if (! $event && $guest->event_id) {
            $event = Event::find($guest->event_id);
        }

        if ($event) {
            $event->loadMissing(['template', 'tenant.defaultTemplate']);
        }

        // 1) Event-selected template
        if ($event?->template) {
            return $event->template;
        }

        // 2) Tenant default template
        if ($event?->tenant?->defaultTemplate) {
            return $event->tenant->defaultTemplate;
        }

        return Template::where('name', $this->template)->first();
    }

    public function generateFromTemplate(Guest $guest, Template $template): string
    {
        $canvas = $this->buildCanvasFromModel($template);
        $this->placeQrCode($canvas, $guest->qr_token, [
            'x' => $template->qr_x,
            'y' => $template->qr_y,
            'size' => $template->qr_size,
        ]);
        $this->placeName($canvas, $guest->name, [
            'x' => $template->name_x,
            'y' => $template->name_y,
            'font_size' => $template->font_size ?? $template->name_font_size ?? 48,
            'font_color' => $template->font_color ?? $template->name_font_color ?? '#1a1a1a',
            'font_path' => $template->name_font_path ?? $template->font_family ?? resource_path('fonts/NotoSans-Regular.ttf'),
            'font_italic' => $template->font_italic ?? false,
            'font_bold' => $template->font_bold ?? false,
            'name_box_width' => $template->name_box_width,
            'alignment' => 'center',
        ]);

        $path = sprintf(
            'cards/%s/%s.jpg',
            $guest->event_id,
            $guest->short_code,
        );

        Storage::disk('local')->makeDirectory(dirname($path));

        $canvas->save(
            Storage::disk('local')->path($path),
            quality: $template->output_quality,
        );

        return Storage::disk('local')->path($path);
    }

    public function preview(Template $template, ?Guest $guest = null): string
    {
        $guest ??= new Guest([
            'name' => 'Guest Name',
            'qr_token' => 'preview-token',
        ]);

        $canvas = $this->buildCanvasFromModel($template);
        $this->placeQrCode($canvas, $guest->qr_token, [
            'x' => $template->qr_x,
            'y' => $template->qr_y,
            'size' => $template->qr_size,
        ]);
        $this->placeName($canvas, $guest->name, [
            'x' => $template->name_x,
            'y' => $template->name_y,
            'font_size' => $template->font_size ?? $template->name_font_size ?? 48,
            'font_color' => $template->font_color ?? $template->name_font_color ?? '#1a1a1a',
            'font_path' => $template->name_font_path ?? $template->font_family ?? resource_path('fonts/NotoSans-Regular.ttf'),
            'font_italic' => $template->font_italic ?? false,
            'font_bold' => $template->font_bold ?? false,
            'name_box_width' => $template->name_box_width,
            'alignment' => 'center',
        ]);

        return $canvas->encodeUsingFormat(
            \Intervention\Image\Format::JPEG,
            quality: 70,
        )->toDataUri()->toString();
    }

    private function buildCanvas(array $template): mixed
    {
        $bg = $template['background'];
        $output = $template['output'];

        if (file_exists($bg)) {
            $canvas = $this->image->decodePath($bg);
            $canvas->cover($output['width'], $output['height']);
        } else {
            $canvas = $this->image->createImage($output['width'], $output['height']);
            $canvas->fill('ffffff');
        }

        return $canvas;
    }

    private function buildCanvasFromModel(Template $template): mixed
    {
        $bg = $template->background_path;

        if ($bg && Storage::disk('public')->exists($bg)) {
            $canvas = $this->image->decodePath(Storage::disk('public')->path($bg));
            $canvas->cover($template->output_width, $template->output_height);
        } elseif ($bg && file_exists($bg)) {
            $canvas = $this->image->decodePath($bg);
            $canvas->cover($template->output_width, $template->output_height);
        } else {
            $canvas = $this->image->createImage($template->output_width, $template->output_height);
            $canvas->fill('ffffff');
        }

        return $canvas;
    }

    private function placeQrCode(mixed $canvas, string $token, array $config): void
    {
        $qrCode = new QrCode($token);
        $writer = new PngWriter;
        $result = $writer->write($qrCode);

        $qrImage = $this->image->decode($result->getImage());
        $qrImage->resize($config['size'], $config['size']);

        $canvas->insert($qrImage, $config['x'], $config['y']);
    }

    private function placeName(mixed $canvas, string $name, array $config): void
    {
        $font = new Font(
            filepath: $config['font_path'],
            size: $config['font_size'],
            color: $config['font_color'],
            alignmentHorizontal: Alignment::create($config['alignment']),
        );

        $canvas->text($name, $config['x'], $config['y'], $font);
    }
}
