<?php

namespace App\Http\Controllers\Lists;

use Illuminate\Http\Request;
use App\Services\GachaLogic;
use App\Http\Requests;
use App\Http\Controllers\Controller;

class Gacha extends Controller
{
    public function index()
    {
    	$myModel = new GachaLogic();
        $result = $myModel->checkGacha();
        return response(json_encode($result[0]),$result[1]);
    }

    public function store()
    {
    	$myModel = new GachaLogic();
        $result = $myModel->drawGacha();
        return response(json_encode($result[0]),$result[1]);
    }
}
