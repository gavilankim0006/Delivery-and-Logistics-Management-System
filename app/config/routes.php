<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/** @var object $router **/

// Default entry
$router->get('/', 'Welcome::index');

// Authentication routes
$router->match('/login', 'Auth::login', 'GET|POST');
$router->match('/register', 'Auth::register', 'GET|POST');
$router->get('/logout', 'Auth::logout');
$router->match('/profile', 'Auth::profile', 'GET|POST');
$router->post('/users/save_token', 'Auth::save_token');

// Admin panel routes
$router->get('/admin', 'Admin::index');
$router->get('/admin/users', 'Admin::users');
$router->post('/admin/create_user', 'Admin::create_user');
$router->post('/admin/toggle_user_status/{id}', 'Admin::toggle_user_status');
$router->get('/admin/riders', 'Admin::riders');
$router->post('/admin/create_rider', 'Admin::create_rider');
$router->post('/admin/update_rider/{id}', 'Admin::update_rider');
$router->post('/admin/delete_rider/{id}', 'Admin::delete_rider');
$router->match('/admin/settings', 'Admin::settings', 'GET|POST');
$router->get('/admin/reports', 'Admin::reports');
$router->get('/admin/logs', 'Admin::logs');

// Dispatch board routes
$router->get('/dispatch', 'Dispatch::index');
