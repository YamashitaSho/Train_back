<?php

namespace App\Http\Controllers\Lists;

use Illuminate\Http\Request;
use App\Services\StageLogic;
use App\Http\Requests;
use App\Http\Controllers\Controller;

class Stage extends Controller
{


    public function __construct(Request $request)
    {
        $user_id = $request->session()->get('user_id');
        $this->service = new StageLogic($user_id);
    }


    public function index()
    {
        $result = $this->service>getBattlelist();
        return \Response::json($result[0],$result[1]);
    }


    public function store()
    {
        $result = $this->service->joinBattle();
        return \Response::json($result[0],$result[1]);
    }
}

