<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Checkin extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'event_id', 'guest_id', 'checked_in_at', 'synced_at'];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
