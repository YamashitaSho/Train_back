<?php

namespace App\Http\Controllers\Lists;

use Illuminate\Http\Request;
use App\Services\MenuLogic;
use App\Http\Requests;
use App\Http\Controllers\Controller;

class Menu extends Controller
{


    public function __construct(Request $request)
    {
        $user_id = $request->session()->get('user_id');
        $this->service = new MenuLogic($user_id);
    }


    public function index()
    {
        $result = $this->service->getMenu();
        return \Response::json($result[0],$result[1]);
    }
}