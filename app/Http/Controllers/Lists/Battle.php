<?php

namespace App\Http\Controllers\Lists;

use Illuminate\Http\Request;
use App\Services\BattleLogic;
use App\Http\Requests;
use App\Http\Controllers\Controller;

class Battle extends Controller
{


    public function show(Request $request)
    {
        $user_id = $request->session()->get('user_id');
        $service = new BattleLogic($user_id);
        $result = $service->turnoverBattle();
        return \Response::json($result[0],$result[1]);
    }


    public function update(Request $request)
    {
        $user_id = $request->session()->get('user_id');
        $service = new BattleLogic($user_id);
        $result = $service->setBattle();
        return \Response::json($result[0],$result[1]);
    }
}
