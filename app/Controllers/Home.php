<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        helper('vite');
        return view('welcome_message');
    }
}
