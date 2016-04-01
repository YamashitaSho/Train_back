<?php

namespace App\Http\Controllers\Lists;

use Illuminate\Http\Request;
use App\Services\ResultLogic;
use App\Http\Requests;
use App\Http\Controllers\Controller;

class Result extends Controller
{
    public function update($battle_id)
    {
    	$myModel = new Resultlogic();
    	$result = $myModel->getResult($battle_id);
        return response(json_encode($result[0]),$result[1]);
    }
}
