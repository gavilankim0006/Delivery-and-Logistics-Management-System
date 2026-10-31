<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Welcome extends Controller {
    public function index() {
        $this->call->helper('auth');
        if (auth_check()) {
            $role = auth_role();
            if ($role === 'admin') {
                redirect('admin');
            } elseif ($role === 'dispatcher') {
                redirect('dispatch');
            } elseif ($role === 'rider') {
                redirect('rider');
            } else {
                redirect('profile');
            }
        } else {
            redirect('login');
        }
    }
}
