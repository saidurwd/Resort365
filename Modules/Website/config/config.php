<?php

return [
    'name' => 'Website',

    /*
    | Languages of the public website (Step 8.2a), code => name in that language. English first: a text without a translation
    | falls back to it.
    */
    'locales' => [
        'en' => 'English',
        'bn' => 'বাংলা',
    ],

    /*
    | How far ahead the "from" price looks for the lowest nightly rate, in days.
    */
    'from_price_days' => 90,
];
