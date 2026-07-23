<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageLog extends Model
{
    protected $table = 'messages_log';

    protected $fillable = [
        'guest_id',
        'channel',
        'status',
        'provider_ref',
        'response',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }
}
