<?php

namespace App\Http\Controllers\Lists;

use Illuminate\Http\Request;
use App\Services\OrderLogic;
use App\Http\Requests;
use App\Http\Controllers\Controller;

class Order extends Controller
{


    public function __construct(Request $request)
    {
        $user_id = $request->session()->get('user_id');
        $this->service = new OrderLogic($user_id);
    }


    public function index()
    {
        $result = $this->service->getOrder();
        return \Response::json($result[0],$result[1]);
    }


    public function update($type)
    {
        $result = $this->service->changeOrder($type);
        return \Response::json($result[0],$result[1]);
    }
}
