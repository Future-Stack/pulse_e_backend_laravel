<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Places API (New)
    |--------------------------------------------------------------------------
    | Used by GooglePlacesClient for places:discover and places:refresh.
    | Enable the "Places API (New)" in Google Cloud Console and restrict the
    | key to server IPs. Same key typically works for Geocoding too, but you
    | can point GOOGLE_GEOCODING_API_KEY at a separately-restricted key.
    */
    'google_places_api_key' => env('GOOGLE_PLACES_API_KEY'),
    'google_geocoding_api_key' => env('GOOGLE_GEOCODING_API_KEY', env('GOOGLE_PLACES_API_KEY')),

    /*
    |--------------------------------------------------------------------------
    | OIG LEIE exclusions list
    |--------------------------------------------------------------------------
    | CMS/OIG publish a monthly full-refresh CSV. URL is stable but verify
    | periodically at https://oig.hhs.gov/exclusions/exclusions_list.asp
    */
    'leie_csv_url' => env('LEIE_CSV_URL', 'https://oig.hhs.gov/exclusions/downloadables/UPDATED.csv'),

    /*
    |--------------------------------------------------------------------------
    | NPPES weekly file index
    |--------------------------------------------------------------------------
    */
    'nppes_index_url' => env('NPPES_INDEX_URL', 'https://download.cms.gov/nppes/NPI_Files.html'),
    'nppes_base_url' => env('NPPES_BASE_URL', 'https://download.cms.gov/nppes/'),

    /*
    |--------------------------------------------------------------------------
    | Places discovery grid
    |--------------------------------------------------------------------------
    | Controls how many search points DiscoverPlacesJob spreads across a
    | metro's radius_km. More rings/points = better coverage, more API spend.
    */
    'discovery_grid_rings' => env('MARKETPLACE_GRID_RINGS', 2),
    'discovery_grid_points_per_ring' => env('MARKETPLACE_GRID_POINTS_PER_RING', 6),

    /*
    |--------------------------------------------------------------------------
    | Places Discovery Guard & Environment Controls
    |--------------------------------------------------------------------------
    | Master switches to prevent accidental or runaway Google Places API sweeps.
    | By default outside of production (local/staging), sweeps are blocked.
    */
    'enable_places_discovery' => env('MARKETPLACE_ENABLE_PLACES_DISCOVERY', true),
    'allow_local_places_discovery' => env('MARKETPLACE_ALLOW_LOCAL_DISCOVERY', false),

    /*
    |--------------------------------------------------------------------------
    | ZIP -> metro resolution cache TTL (days)
    |--------------------------------------------------------------------------
    */
    'zip_metro_cache_days' => env('MARKETPLACE_ZIP_CACHE_DAYS', 90),

];
