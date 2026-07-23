<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Services\CardGenerationService;
use Illuminate\Http\Request;

class GuestInvitationController extends Controller
{
    public function show(Request $request, string $short_code, CardGenerationService $cards)
    {
        $guest = Guest::with(['event.template', 'event.tenant.defaultTemplate'])
            ->where('short_code', $short_code)
            ->orWhere('qr_token', $short_code)
            ->first();

        if (! $guest) {
            return response()->view('guest-invitation', [
                'notFound' => true,
            ], 404);
        }

        $cardPath = $cards->generate($guest);
        $filename = 'invitation-' . ($guest->short_code ?: $guest->id) . '.jpg';

        if ($request->boolean('download')) {
            return response()->download($cardPath, $filename, [
                'Content-Type' => mime_content_type($cardPath) ?: 'image/jpeg',
            ]);
        }

        $binary = file_get_contents($cardPath);
        if ($binary === false) {
            abort(500, 'Unable to render invitation card.');
        }

        $mime = mime_content_type($cardPath) ?: 'image/jpeg';

        return view('guest-invitation', [
            'notFound' => false,
            'guest' => $guest,
            'cardDataUri' => 'data:' . $mime . ';base64,' . base64_encode($binary),
            'downloadUrl' => route('guest.invitation', ['short_code' => $short_code, 'download' => 1]),
        ]);
    }
}
