<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MobilePosController extends Controller
{
    /**
     * Show the mobile POS login page.
     *
     * @return \Illuminate\View\View
     */
    public function login()
    {
        return view('mobile-pos.login');
    }

    /**
     * Show the mobile POS main page.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('mobile-pos.index');
    }
}