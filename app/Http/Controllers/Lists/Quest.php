<?php

namespace App\Http\Controllers\Lists;

use Illuminate\Http\Request;
use App\Services\QuestLogic;
use App\Http\Requests;
use App\Http\Controllers\Controller;
class Quest extends Controller
{
    public function store()
    {
    	$myModel = new QuestLogic();
    	$result = $myModel->joinQuest();
        return response(json_encode($result[0]),$result[1]);
    }
}
