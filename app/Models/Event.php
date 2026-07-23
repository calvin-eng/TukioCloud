<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'date', 'venue', 'language', 'delivery_channels', 'template_id'];

    protected $attributes = [
        'language' => 'sw',
        'delivery_channels' => '["sms_beem","whatsapp_api","whatsapp_manual_export"]',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'delivery_channels' => 'array',
        ];
    }

    public function guests()
    {
        return $this->hasMany(Guest::class);
    }

    public function template()
    {
        return $this->belongsTo(Template::class);
    }
}
