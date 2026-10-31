<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/** @var object $router **/

// Default entry
$router->get('/', 'Welcome::index');
$router->get('/health', 'Health::index');

// Authentication routes
$router->match('/login', 'Auth::login', 'GET|POST');
$router->match('/register', 'Auth::register', 'GET|POST');
$router->get('/logout', 'Auth::logout');
$router->match('/profile', 'Auth::profile', 'GET|POST');
$router->post('/users/save_token', 'Auth::save_token');
$router->post('/auth/save_token', 'Auth::save_token');
$router->post('/auth/test_push', 'Auth::test_push');
$router->post('/auth/test_sms', 'Auth::test_sms');

// Admin panel routes
$router->get('/admin', 'Admin::index');
$router->get('/admin/users', 'Admin::users');
$router->post('/admin/create_user', 'Admin::create_user');
$router->get('/admin/users/{id}', 'Admin::show_user');
$router->post('/admin/users/{id}/update', 'Admin::update_user');
$router->post('/admin/users/{id}/delete', 'Admin::delete_user');
$router->post('/admin/toggle_user_status/{id}', 'Admin::toggle_user_status');
$router->get('/admin/riders', 'Admin::riders');
$router->post('/admin/create_rider', 'Admin::create_rider');
$router->post('/admin/update_rider/{id}', 'Admin::update_rider');
$router->post('/admin/delete_rider/{id}', 'Admin::delete_rider');
$router->post('/admin/riders/{id}/link-account', 'Admin::link_rider_account');
$router->post('/admin/riders/{id}/unlink-account', 'Admin::unlink_rider_account');
$router->match('/admin/settings', 'Admin::settings', 'GET|POST');
$router->post('/admin/dispatch-outbox', 'Admin::dispatch_outbox');
$router->post('/admin/test-sms', 'Admin::test_sms');
$router->post('/admin/test-push', 'Admin::test_push');
$router->get('/admin/reports', 'Admin::reports');
$router->get('/admin/logs', 'Admin::logs');
$router->get('/admin/courier-simulator', 'Admin::courier_simulator');
$router->get('/admin/service-areas', 'Admin::service_areas');
$router->post('/admin/service-areas/save', 'Admin::save_service_area');
$router->post('/admin/service-areas/delete/{id}', 'Admin::delete_service_area');
$router->get('/admin/command-simulator', 'Admin::command_simulator');
$router->post('/admin/command-simulator', 'Admin::run_command_simulation');
$router->post('/admin/courier-simulator/book/{id}', 'Admin::book_courier_order');
$router->post('/admin/courier-simulator/{id}', 'Admin::simulate_courier_event');
$router->post('/webhooks/paymongo', 'Webhooks::paymongo');
$router->post('/webhooks/lalamove', 'Webhooks::lalamove');
$router->get('/delivery-control/{token}', 'Delivery_control::show');
$router->post('/delivery-control/{token}/submit', 'Delivery_control::submit');

// Customer saved-address CRUD
$router->get('/addresses', 'Addresses::index');
$router->get('/addresses/new', 'Addresses::new_address');
$router->post('/addresses', 'Addresses::create');
$router->get('/addresses/{id}/edit', 'Addresses::edit');
$router->post('/addresses/{id}/update', 'Addresses::update');
$router->post('/addresses/{id}/delete', 'Addresses::delete');

// Customer and staff order views
$router->get('/orders', 'Orders::index');
$router->get('/orders/new', 'Orders::new_order');
$router->post('/orders', 'Orders::create');
$router->get('/orders/{id}/edit', 'Orders::edit');
$router->post('/orders/{id}/edit', 'Orders::update');
$router->post('/orders/{id}/delete', 'Orders::delete_draft');
$router->post('/orders/{id}/checkout', 'Orders::checkout');
$router->post('/orders/{id}/cancel', 'Orders::cancel');
$router->get('/orders/{id}/location', 'Orders::location');
$router->post('/orders/{id}/control-pass', 'Orders::issue_control_pass');
$router->post('/orders/{id}/control-pass/revoke', 'Orders::revoke_control_pass');
$router->get('/orders/{id}', 'Orders::show');

// Dispatch board routes
$router->get('/dispatch', 'Dispatch::index');
$router->get('/dispatch/data', 'Dispatch::data');
$router->post('/dispatch/recover/{id}', 'Dispatch::recover');
$router->post('/dispatch/orders/{id}/assign-internal', 'Dispatch::assign_internal');
$router->post('/dispatch/orders/{id}/select-lalamove', 'Dispatch::select_lalamove');
$router->post('/dispatch/requests/{id}/review', 'Dispatch::review_request');

// Internal rider portal
$router->get('/rider', 'Rider::index');
$router->get('/rider/orders/{id}', 'Rider::show');
$router->post('/rider/orders/{id}/pickup', 'Rider::pickup');
$router->post('/rider/orders/{id}/location', 'Rider::location');
$router->post('/rider/orders/{id}/complete', 'Rider::complete');
$router->get('/orders/{id}/proof', 'Delivery_proofs::show');

// Route Calculation & Places API
$router->post('/api/route/calculate', 'Route_api::calculate');
$router->get('/api/places/autocomplete', 'Route_api::autocomplete');

// AI Assistant routes
$router->post('/ai/chat', 'Ai::chat');
$router->get('/ai/history', 'Ai::history');


