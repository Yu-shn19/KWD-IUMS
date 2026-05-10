<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Excluded meter reader identifiers (case-insensitive)
    |--------------------------------------------------------------------------
    |
    | Users with role reader are hidden from meter reading / download-reading
    | lists if their email local-part (before @), full email, or single "name"
    | field matches any of these tokens.
    |
    */
    'excluded_reader_identifiers' => [
        'KEYVENZONE4',
        'KEYVEN',
        'KEYVENZONE5',
        'KEYVENZONE6',
        'SHUWNZONE2, SHUWN',
        'SHUWNZONE1, SHUWN',
        'SHUWNZONE2, SHUWN',
        'SHUWNZONE3, SHUWN',
        'SHUWNZONE9, SHUWN',
        'PLAZOS ZONE8, SHUWN',
    ],

];
