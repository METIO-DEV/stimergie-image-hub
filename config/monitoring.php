<?php

return [
    'service' => env('MONITORING_SERVICE', 'stimergie-image-hub'),

    'token' => env('MONITORING_OPS_TOKEN'),

    'release' => env('MONITORING_RELEASE'),

    'queue_names' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('MONITORING_QUEUE_NAMES', 'default,sync')),
    ))),
];
