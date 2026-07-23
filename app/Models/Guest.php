<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Database\Factories\GuestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    /** @use HasFactory<GuestFactory> */
    use HasFactory, BelongsToTenant;

    protected $fillable = ['tenant_id', 'event_id', 'name', 'phone', 'has_whatsapp', 'notes', 'qr_token', 'short_code'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function messageLogs()
    {
        return $this->hasMany(MessageLog::class);
    }
}
