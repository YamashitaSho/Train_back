<?php

namespace App\Http\Controllers\Lists;

use Illuminate\Http\Request;
use App\Services\MenuLogic;
use App\Http\Requests;
use App\Http\Controllers\Controller;
class Menu extends Controller
{
    public function index()
    {
    	$myModel = new MenuLogic();
    	$result = $myModel->getMenu();
    	return \Response::json($result[0],$result[1]);
    }
}
