<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Firebase Cloud Messaging Configuration
|--------------------------------------------------------------------------
|
| Configuration for Firebase push notifications and Web Push VAPID credentials.
|
*/
$config['firebase_push_enabled'] = getenv('FIREBASE_PUSH_ENABLED') === '1';
$config['firebase_project_id'] = getenv('FIREBASE_PROJECT_ID') ?: '';
$config['firebase_client_email'] = getenv('FIREBASE_CLIENT_EMAIL') ?: '';
$config['firebase_private_key'] = getenv('FIREBASE_PRIVATE_KEY') ?: '';
$config['firebase_credentials_path'] = getenv('FIREBASE_CREDENTIALS_PATH') ?: '';
$config['firebase_vapid_key'] = getenv('FIREBASE_VAPID_KEY') ?: '';
$config['webpush_vapid_private_key'] = getenv('WEBPUSH_VAPID_PRIVATE_KEY') ?: '';
$config['webpush_vapid_subject'] = getenv('WEBPUSH_VAPID_SUBJECT') ?: '';
