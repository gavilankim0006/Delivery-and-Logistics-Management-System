<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| PayMongo Gateway Configuration
|--------------------------------------------------------------------------
|
| Configuration for PayMongo checkout sessions, webhooks, and public keys.
|
*/
$config['paymongo_secret_key'] = getenv('PAYMONGO_SECRET_KEY') ?: '';
$config['paymongo_public_key'] = getenv('PAYMONGO_PUBLIC_KEY') ?: '';
$config['paymongo_base_url'] = getenv('PAYMONGO_BASE_URL') ?: 'https://api.paymongo.com/v1';
$config['paymongo_webhook_secret'] = getenv('PAYMONGO_WEBHOOK_SECRET') ?: '';
