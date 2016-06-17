<?php

namespace App\Http\Controllers\Lists;

use Illuminate\Http\Request;
use App\Services\ResultLogic;
use App\Http\Requests;
use App\Http\Controllers\Controller;

class Result extends Controller
{


    public function update(Request $request)
    {
        $user_id = $request->session()->get('user_id');
        $service = new ResultLogic($user_id);
        $result = $service->getResult();
        return \Response::json($result[0],$result[1]);
    }
}