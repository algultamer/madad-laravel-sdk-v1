<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Connection
    |--------------------------------------------------------------------------
    | Your partner API key (issued by the Madad team) and the Madad base URL.
    | The key authenticates as: Authorization: Bearer <key>.
    */
    'api_key' => env('MADAD_API_KEY'),
    'base_url' => env('MADAD_BASE_URL', 'https://madad-app.com/api/v1'),
    'timeout' => (int) env('MADAD_TIMEOUT', 30),

    // Master switch — set false to disable all auto-sync (e.g. during seeding).
    'enabled' => (bool) env('MADAD_SYNC_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    | Recommended: run every sync through a queued job so your app stays fast
    | and transient failures retry. Set enabled=false to push inline (synchronous).
    */
    'queue' => [
        'enabled' => (bool) env('MADAD_QUEUE', true),
        'connection' => env('MADAD_QUEUE_CONNECTION'),
        'name' => env('MADAD_QUEUE_NAME', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Product mapping
    |--------------------------------------------------------------------------
    | Map Madad's fields to YOUR model. Values are dot-paths resolved with
    | data_get() (so relations work: 'category.name'). Strings only — no
    | closures (keeps config:cache working). For computed values, add a
    | madad{Field}() method to your model; the SDK prefers it over the map.
    |
    | Madad-controlled fields (type, brand, delivery_type) are NOT sent —
    | they come from your partner account configuration.
    */
    'product' => [

        // Your Eloquent model, e.g. \App\Models\Product::class
        'model' => null,

        // YOUR stable id column → Madad's external_id (the matching key).
        'external_id' => 'id',

        // Concurrency: a column whose value increases on each change. A date
        // column (e.g. updated_at) is converted to an integer timestamp.
        'version' => 'updated_at',

        // What to do when your record is deleted: 'delete'.
        'on_delete' => 'delete',

        // Flat fields:  madad_field => your dot-path
        'map' => [
            'name' => 'name',
            // 'name_en'       => 'name_en',
            // 'description'   => 'description',
            'price' => 'price',
            // 'price_unit'    => 'unit',
            // 'min_order_qty' => 'min_qty',
            // 'is_active'     => 'is_published',
        ],

        // Category: external_id (your category id) + path (hierarchy under your
        // Madad parent category). Each path entry is a dot-path.
        'category' => [
            'external_id' => null,           // e.g. 'category.code'
            'path' => [],                    // e.g. ['category.parent.name', 'category.name']
        ],

        // Specifications (1:many). Point at a relation + the key/value columns.
        'specifications' => [
            // 'relation' => 'attributes',
            // 'key'      => 'name',
            // 'value'    => 'value',
        ],

        // Images (1:many). Point at a relation + the url (+ optional sort) columns.
        'images' => [
            // 'relation' => 'photos',
            // 'url'      => 'url',
            // 'sort'     => 'position',
        ],
    ],
];
