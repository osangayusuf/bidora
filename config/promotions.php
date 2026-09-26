<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Winner Popup
    |--------------------------------------------------------------------------
    | Set winner_id to the ID of a winner (auction with a winner) to display
    | a congratulatory popup on the homepage. Set to null to disable.
    */
    'winner_popup' => [
        'enabled' => true,
        'winner_id' => 66, // Auction ID whose winner should be featured
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Popup
    |--------------------------------------------------------------------------
    | Set auction_id to the ID of an active/triggered auction to show an
    | event promotion popup on the homepage. Set to null to disable.
    */
    'event_popup' => [
        'enabled' => true,
        'auction_id' => 2139, // Auction ID to promote as a special event
    ],
];
