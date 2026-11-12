<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Vue_app extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->helper('auth');
    }

    public function index()
    {
        $user = auth_check() ? auth_user() : null;

        $this->call->view('vue_app/index', [
            'page_title' => 'Vue Application Shell',
            'base_url' => base_url(),
            'csrf_token' => csrf_token(),
            'initial_state' => [
                'user' => $user,
                'route' => '/app',
                'baseUrl' => base_url(),
            ],
        ]);
    }
}
