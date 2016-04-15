<?php

namespace App\Http\Controllers\Lists;

use Illuminate\Http\Request;
use App\Services\BattleLogic;
use App\Http\Requests;
use App\Http\Controllers\Controller;
class Battle extends Controller
{
    public function show($battle_id)
    {
    #    $user_id = \Session->getUserID();
        $result = $this->getModel()->turnoverBattle($battle_id);
        return \Response::json($result[0],$result[1]);
    }

    public function update($battle_id)
    {
        $result = $this->getModel()->setBattle($battle_id);
        return \Response::json($result[0],$result[1]);
    }
    private function getModel()
    {
        return new BattleLogic();
    }
}
