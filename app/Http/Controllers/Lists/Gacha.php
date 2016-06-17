<?php

namespace App\Http\Controllers\Lists;

use Illuminate\Http\Request;
use App\Services\GachaLogic;
use App\Http\Requests;
use App\Http\Controllers\Controller;

class Gacha extends Controller
{


    public function index(Request $request)
    {
        $user_id = $request->session()->get('user_id');
        $service = new GachaLogic($user_id);
        $result = $service->checkGacha();
        return \Response::json($result[0],$result[1]);
    }


    public function store(Request $request)
    {
        $user_id = $request->session()->get('user_id');
        $service = new GachaLogic($user_id);
        $result = $service->drawGacha();
        return \Response::json($result[0],$result[1]);
    }
}
