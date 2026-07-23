<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\CardGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ExportEventCommand extends Command
{
    protected $signature = 'events:export {event : Event ID}';
    protected $description = 'Export event guest cards as ZIP + CSV for manual WhatsApp sending';

    public function handle(CardGenerationService $cards): int
    {
        $event = Event::with('guests')->findOrFail((int) $this->argument('event'));

        $zip = new \ZipArchive;
        $zipPath = Storage::disk('local')->path("exports/{$event->id}/cards.zip");
        Storage::disk('local')->makeDirectory("exports/{$event->id}");

        if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
            $this->error('Failed to create ZIP archive');
            return 1;
        }

        $csv = fopen(Storage::disk('local')->path("exports/{$event->id}/guests.csv"), 'w');
        fputcsv($csv, ['Name', 'Phone', 'Short Code']);

        foreach ($event->guests as $guest) {
            $cardPath = $cards->generate($guest);
            $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $guest->name) . '-' . $guest->short_code . '.jpg';
            $zip->addFile($cardPath, $filename);

            fputcsv($csv, [$guest->name, $guest->phone, $guest->short_code]);
        }

        fclose($csv);
        $zip->addFile(Storage::disk('local')->path("exports/{$event->id}/guests.csv"), 'guests.csv');
        $zip->close();

        $this->info("Export saved to: $zipPath");
        $this->info("CSV saved to: " . Storage::disk('local')->path("exports/{$event->id}/guests.csv"));

        return 0;
    }
}
