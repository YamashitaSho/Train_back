<?php

namespace App\Http\Controllers\Lists;

use Illuminate\Http\Request;
use App\Services\BattleLogic;
use App\Http\Requests;
use App\Http\Controllers\Controller;

class Battle extends Controller
{


    public function __construct(Request $request)
    {
        $user_id = $request->session()->get('user_id');
        $this->service = new BattleLogic($user_id);
    }


    public function show($battle_id)
    {
        $result = $this->service->turnoverBattle($battle_id);
        return \Response::json($result[0],$result[1]);
    }


    public function update($battle_id)
    {
        $result = $this->service->setBattle($battle_id);
        return \Response::json($result[0],$result[1]);
    }
}
