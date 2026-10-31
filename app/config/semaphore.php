<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Semaphore SMS Configuration
|--------------------------------------------------------------------------
|
| Configuration for Semaphore SMS notification delivery.
|
*/
$config['semaphore_enabled'] = (bool)(getenv('SEMAPHORE_ENABLED') ?: 0);
$config['semaphore_api_key'] = getenv('SEMAPHORE_API_KEY') ?: '';
$config['semaphore_sender_name'] = getenv('SEMAPHORE_SENDER_NAME') ?: 'SEMAPHORE';
$config['semaphore_base_url'] = getenv('SEMAPHORE_BASE_URL') ?: 'https://api.semaphore.co/api/v4';
