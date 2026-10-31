<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Courier Provider Configuration
|--------------------------------------------------------------------------
|
| Configuration for delivery courier drivers (mock, lalamove).
|
*/
$config['courier_driver'] = getenv('COURIER_DRIVER') ?: 'mock';
$config['lalamove_api_key'] = getenv('LALAMOVE_API_KEY') ?: '';
$config['lalamove_api_secret'] = getenv('LALAMOVE_API_SECRET') ?: '';
$config['lalamove_base_url'] = getenv('LALAMOVE_BASE_URL') ?: 'https://rest.sandbox.lalamove.com';
$config['lalamove_market'] = getenv('LALAMOVE_MARKET') ?: 'PH';
$config['lalamove_webhook_secret'] = getenv('LALAMOVE_WEBHOOK_SECRET') ?: '';
$config['lalamove_webhook_auth_confirmed'] = (bool)(getenv('LALAMOVE_WEBHOOK_AUTH_CONFIRMED') ?: 0);
