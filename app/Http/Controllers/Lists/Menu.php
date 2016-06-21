<?php

namespace App\Http\Controllers\Lists;

use Illuminate\Http\Request;
use App\Services\MenuLogic;
use App\Http\Requests;
use App\Http\Controllers\Controller;

class Menu extends Controller
{


    public function index(Request $request)
    {
        $user_id = $request->session()->get('user_id');
        $service = new MenuLogic($user_id);
        $result = $service->getMenu();
        return \Response::json($result[0],$result[1]);
    }
}