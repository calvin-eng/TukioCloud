<?php

namespace App\Imports;

use App\Models\Guest;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class GuestImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public array $skipped = [];

    public array $columnMap = [
        'name' => null,
        'phone' => null,
        'notes' => null,
    ];

    public function __construct(
        public int $eventId,
        public int $tenantId,
    ) {}

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $this->detectColumns($rows->first());

        if (!$this->columnMap['name'] || !$this->columnMap['phone']) {
            $this->skipped[] = [
                'name' => '—',
                'phone' => '—',
                'reason' => 'Could not detect required columns. Ensure the file has headers named "Name" and "Phone" (or similar).',
            ];
            return;
        }

        $existingPhones = Guest::where('tenant_id', $this->tenantId)
            ->where('event_id', $this->eventId)
            ->pluck('phone')
            ->map(fn ($p) => $this->normalizePhone($p))
            ->toArray();

        $seenPhones = [];

        foreach ($rows as $row) {
            $rowArray = $row->toArray();

            $name = trim($rowArray[$this->columnMap['name']] ?? '');
            $phone = trim($rowArray[$this->columnMap['phone']] ?? '');
            $notes = $this->columnMap['notes']
                ? trim($rowArray[$this->columnMap['notes']] ?? '')
                : '';

            if (empty($name) || empty($phone)) {
                $this->skipped[] = [
                    'name' => $name ?: '(empty)',
                    'phone' => $phone ?: '(empty)',
                    'reason' => 'Missing required name or phone.',
                ];
                continue;
            }

            $normalizedPhone = $this->normalizePhone($phone);

            if (in_array($normalizedPhone, $existingPhones, true)) {
                $this->skipped[] = [
                    'name' => $name,
                    'phone' => $phone,
                    'reason' => 'Phone already exists for this event.',
                ];
                continue;
            }

            if (in_array($normalizedPhone, $seenPhones, true)) {
                $this->skipped[] = [
                    'name' => $name,
                    'phone' => $phone,
                    'reason' => 'Duplicate phone in import file.',
                ];
                continue;
            }

            $seenPhones[] = $normalizedPhone;

            Guest::create([
                'tenant_id' => $this->tenantId,
                'event_id' => $this->eventId,
                'name' => $name,
                'phone' => $phone,
                'has_whatsapp' => false,
                'notes' => $notes ?: null,
                'qr_token' => Str::random(32),
                'short_code' => strtoupper(Str::random(8)),
            ]);

            $this->created++;
        }
    }

    private function detectColumns(Collection $firstRow): void
    {
        $headers = array_keys($firstRow->toArray());

        $namePatterns = ['name', 'names', 'fullname', 'full_name', 'guest_name', 'guestname', 'full name'];
        $phonePatterns = ['phone', 'phones', 'telephone', 'mobile', 'cell', 'contact', 'phone_number', 'phonenumber', 'tel', 'phone no', 'phone no.'];
        $notesPatterns = ['notes', 'note', 'comments', 'comment', 'remarks', 'remark', 'extra', 'description', 'additional notes', 'notes/comments'];

        foreach ($headers as $header) {
            $lower = strtolower(trim($header));

            if ($this->columnMap['name'] === null) {
                foreach ($namePatterns as $pattern) {
                    if ($lower === $pattern || str_replace('_', ' ', $lower) === $pattern) {
                        $this->columnMap['name'] = $header;
                        break;
                    }
                }
            }

            if ($this->columnMap['phone'] === null) {
                foreach ($phonePatterns as $pattern) {
                    if ($lower === $pattern || str_replace('_', ' ', $lower) === $pattern) {
                        $this->columnMap['phone'] = $header;
                        break;
                    }
                }
            }

            if ($this->columnMap['notes'] === null) {
                foreach ($notesPatterns as $pattern) {
                    if ($lower === $pattern || str_replace('_', ' ', $lower) === $pattern) {
                        $this->columnMap['notes'] = $header;
                        break;
                    }
                }
            }
        }
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }
}
