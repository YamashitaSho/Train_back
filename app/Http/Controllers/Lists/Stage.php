<?php

namespace App\Http\Controllers\Lists;

use Illuminate\Http\Request;
use App\Services\StageLogic;
use App\Http\Requests;
use App\Http\Controllers\Controller;

class Stage extends Controller
{


    public function index(Request $request)
    {
        $user_id = $request->session()->get('user_id');
        $service = new StageLogic($user_id);
        $result = $service->getBattlelist();
        return \Response::json($result[0],$result[1]);
    }


    public function store(Request $request)
    {
        $user_id = $request->session()->get('user_id');
        $service = new StageLogic($user_id);
        $result = $service->joinBattle();
        return \Response::json($result[0],$result[1]);
    }
}

