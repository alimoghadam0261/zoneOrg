<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    | Raw GPS pings are kept for this many days; older rows are removed by
    | `php artisan zone:purge-old-pings` (scheduled daily).
    */
    'retention_days' => (int) env('ZONE_RETENTION_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Debounce / false-alarm suppression
    |--------------------------------------------------------------------------
    */
    // A fix is only trusted for violation triggering below this accuracy.
    'min_accuracy_meters' => (float) env('ZONE_MIN_ACCURACY_METERS', 25),

    // Number of consecutive fixes inside a forbidden zone before a violation fires.
    'confirm_pings' => (int) env('ZONE_CONFIRM_PINGS', 2),

    // Minimum gap between two `violation_lingering` events for the same zone.
    'lingering_interval_seconds' => (int) env('ZONE_LINGERING_INTERVAL', 60),

    /*
    |--------------------------------------------------------------------------
    | Presence
    |--------------------------------------------------------------------------
    */
    'offline_after_minutes' => (int) env('ZONE_OFFLINE_AFTER_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | Performance
    |--------------------------------------------------------------------------
    */
    'zone_cache_ttl' => (int) env('ZONE_ZONE_CACHE_TTL', 60),
    'state_ttl_seconds' => 3600,
    'ping_max_per_second' => (int) env('ZONE_PING_MAX_PER_SECOND', 20),
    'max_candidates' => (int) env('ZONE_MAX_CANDIDATES', 200),

    /*
    |--------------------------------------------------------------------------
    | Map themes
    |--------------------------------------------------------------------------
    */
    'tile_layers' => [
        'street' => [
            'label' => 'خیابان',
            'url' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            'attribution' => '&copy; OpenStreetMap contributors',
            'max_zoom' => 19,
        ],
        // Esri World_Imagery returns 403 for this network, so we use Google's
        // public imagery endpoint instead (verified reachable: 200 image/jpeg).
        'satellite' => [
            'label' => 'ماهواره‌ای',
            'url' => 'https://mt1.google.com/vt/lyrs=s&x={x}&y={y}&z={z}',
            'attribution' => 'Imagery &copy; Google',
            'max_zoom' => 19,
        ],
        'dark' => [
            'label' => 'صنعتی تیره',
            'url' => 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
            'attribution' => '&copy; OpenStreetMap contributors &copy; CARTO',
            'max_zoom' => 20,
        ],
        'light' => [
            'label' => 'روشن پرکنتراست',
            'url' => 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
            'attribution' => '&copy; OpenStreetMap contributors &copy; CARTO',
            'max_zoom' => 20,
        ],
    ],

    'map' => [
        'center' => [35.6892, 51.3890],
        'zoom' => 13,
    ],
];
