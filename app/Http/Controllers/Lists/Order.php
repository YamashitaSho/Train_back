<?php

namespace App\Http\Controllers\Lists;

use Illuminate\Http\Request;
use App\Services\OrderLogic;
use App\Http\Requests;
use App\Http\Controllers\Controller;

class Order extends Controller
{


    public function index(Request $request)
    {
        $user_id = $request->session()->get('user_id');
        $service = new OrderLogic($user_id);
        $result = $service->getOrder();
        return \Response::json($result[0],$result[1]);
    }


    public function update(Request $request, $type)
    {
        $user_id = $request->session()->get('user_id');
        $service = new OrderLogic($user_id);
        $result = $service->changeOrder($type);
        return \Response::json($result[0],$result[1]);
    }
}
