<?php

namespace App\Http\Controllers\Lists;

use Illuminate\Http\Request;
use App\Services\ResultLogic;
use App\Http\Requests;
use App\Http\Controllers\Controller;

class Result extends Controller
{


    public function __construct(Request $request)
    {
        $user_id = $request->session()->get('user_id');
        $this->service = new ResultLogic($user_id);
    }


    public function update($battle_id)
    {
        $result = $this->service->getResult($battle_id);
        return \Response::json($result[0],$result[1]);
    }
}