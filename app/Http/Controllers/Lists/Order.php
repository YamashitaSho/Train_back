<?php

namespace App\Http\Controllers\Lists;

use Illuminate\Http\Request;
use App\Services\OrderLogic;
use App\Http\Requests;
use App\Http\Controllers\Controller;
class Order extends Controller
{

    public function index()
    {
    	$myModel = new OrderLogic();
        $result = $myModel->getOrder();
        return response(json_encode($result[0]),$result[1]);
    }

    public function update($type)
    {
    	$myModel = new OrderLogic();
        $result = $myModel->changeOrder($type);
        return response(json_encode($result[0]),$result[1]);

    }
}
