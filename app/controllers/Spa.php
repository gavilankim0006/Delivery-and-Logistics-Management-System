<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Spa extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->helper('auth');
        require_role(['customer', 'dispatcher', 'admin', 'rider']);
    }

    public function index()
    {
        $this->call->view('spa/index', [
            'page_title' => 'Vue SPA Workspace',
            'base_url' => base_url(),
            'csrf_token' => csrf_token(),
            'auth_user' => auth_user(),
        ]);
    }
}
