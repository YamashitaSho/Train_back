<?php

namespace App\Http\Controllers\Lists;

use Illuminate\Http\Request;
use App\Services\StageLogic;
use App\Http\Requests;
use App\Http\Controllers\Controller;

class Stage extends Controller
{
    public function index()
    {
    	$myModel = new StageLogic();
        $result = $myModel->getBattlelist();
        return response(json_encode($result[0]),$result[1]);
    }
    public function store()
    {
    	$myModel = new StageLogic();
        $result = $myModel->joinBattle();
        return response(json_encode($result[0]),$result[1]);
    }
}

