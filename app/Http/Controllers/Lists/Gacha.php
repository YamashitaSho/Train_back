<?php

namespace App\Http\Controllers\Lists;

use Illuminate\Http\Request;
use App\Services\GachaLogic;
use App\Http\Requests;
use App\Http\Controllers\Controller;

class Gacha extends Controller
{


    public function __construct(Request $request)
    {
        $user_id = $request->session()->get('user_id');
        $this->service = new GachaLogic($user_id);
    }


    public function index()
    {
        $result = $this->service->checkGacha();
        return \Response::json($result[0],$result[1]);
    }


    public function store()
    {
        $result = $this->service->drawGacha();
        return \Response::json($result[0],$result[1]);
    }
}
