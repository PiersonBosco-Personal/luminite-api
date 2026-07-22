<?php

return [

    // Which AIProvider implementation to bind. Only 'openai' is built in Phase 4.
    'provider' => env('AI_PROVIDER', 'openai'),

    'openai' => [
        'key'         => env('AI_OPENAI_KEY'),
        'embed_model' => env('AI_OPENAI_EMBED_MODEL', 'text-embedding-3-small'),
        'base_url'    => env('AI_OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],

];
