<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    protected $fillable = [
        'name',
        'background_path',
        'name_x',
        'name_y',
        'qr_x',
        'qr_y',
        'qr_size',
        'name_font_color',
        'name_font_size',
        'name_font_path',
        'output_width',
        'output_height',
        'output_quality',
    ];

    protected $attributes = [
        'output_width' => 1080,
        'output_height' => 1350,
    ];
}
