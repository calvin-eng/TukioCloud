<?php

return [
    'default' => [
        'background' => storage_path('app/templates/default-bg.jpg'),

        'output' => [
            'width' => 1080,
            'height' => 1350,
            'format' => 'jpg',
            'quality' => 90,
        ],

        'qr_code' => [
            'x' => 390,
            'y' => 950,
            'size' => 300,
        ],

        'name' => [
            'x' => 540,
            'y' => 600,
            'font_size' => 48,
            'font_color' => '#1a1a1a',
            'font_path' => resource_path('fonts/NotoSans-Regular.ttf'),
            'alignment' => 'center',
            'max_width' => 1000,
        ],
    ],
];
