<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Google Maps Platform Configuration
|--------------------------------------------------------------------------
|
| Configuration for Google Maps routing, geocoding, and places autocomplete.
|
*/
$config['google_maps_api_key'] = getenv('GOOGLE_MAPS_API_KEY') ?: '';
$config['google_maps_maps_js_api_key'] = getenv('GOOGLE_MAPS_MAPS_JS_API_KEY') ?: '';
