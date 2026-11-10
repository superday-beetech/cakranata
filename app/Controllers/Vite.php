<?php

namespace App\Controllers;

class Vite extends BaseController
{
    public function index(): string
    {
        helper('vite');
        return view('vite');
    }
}
