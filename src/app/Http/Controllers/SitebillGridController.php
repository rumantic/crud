<?php
namespace Sitebill\Http\Controllers;

use Illuminate\Routing\Controller;

class SitebillGridController extends Controller
{

    public function index()
    {
        return view('sitebill::index');
    }

}
