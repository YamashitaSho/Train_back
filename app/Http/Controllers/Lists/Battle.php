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
    	$myModel = new BattleLogic();
        $result = $myModel->turnoverBattle($battle_id);
        return response(json_encode($result[0],JSON_PRETTY_PRINT),$result[1]);
    }

    public function update($battle_id)
    {
    	$myModel = new BattleLogic();
        $result = $myModel->setBattle($battle_id);
        return response(json_encode($result[0], JSON_PRETTY_PRINT),$result[1]);
    }
}
