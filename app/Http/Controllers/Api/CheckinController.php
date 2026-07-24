<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Models\Guest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckinController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'guest_token' => 'required|string',
            'client_timestamp' => 'nullable|date',
            'event_id' => 'nullable|integer',
        ]);

        $tenantId = session('tenant_id');
        if (!$tenantId) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $guestQuery = Guest::where(function ($q) use ($request) {
            $q->where('qr_token', $request->guest_token)
              ->orWhere('short_code', $request->guest_token);
        })->where('tenant_id', $tenantId);

        if ($request->filled('event_id')) {
            $guestQuery->where('event_id', $request->event_id);
        }

        $guest = $guestQuery->first();

        if (!$guest) {
            return response()->json(['message' => 'Invalid guest token'], 404);
        }

        $checkin = Checkin::where('guest_id', $guest->id)->first();

        if (!$checkin) {
            try {
                $checkin = Checkin::create([
                    'tenant_id' => $tenantId,
                    'event_id' => $guest->event_id,
                    'guest_id' => $guest->id,
                    'checked_in_at' => $request->client_timestamp ?: now(),
                    'synced_at' => now(),
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                if (str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), 'Duplicate')) {
                    $checkin = Checkin::where('guest_id', $guest->id)->firstOrFail();
                } else {
                    throw $e;
                }
            }
        }

        $created = $checkin->wasRecentlyCreated;

        return response()->json([
            'message' => $created ? 'Checked in successfully' : 'Already checked in',
            'checkin' => [
                'id' => $checkin->id,
                'guest_id' => $checkin->guest_id,
                'checked_in_at' => $checkin->checked_in_at,
            ],
            'guest' => [
                'id' => $guest->id,
                'name' => $guest->name,
            ],
        ], $created ? 201 : 200);
    }
}
