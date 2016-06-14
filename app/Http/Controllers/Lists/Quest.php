<?php

namespace App\Http\Controllers\Lists;

use Illuminate\Http\Request;
use App\Services\QuestLogic;
use App\Http\Requests;
use App\Http\Controllers\Controller;

class Quest extends Controller
{


    public function __construct(Request $request)
    {
        $user_id = $request->session()->get('user_id');
        $this->service = new QuestLogic($user_id);
    }


    public function index()
    {
        $result = $this->service->getParty();
        return \Response::json($result[0], $result[1]);
    }


    public function store()
    {
        $result = $this->service->joinQuest();
        return \Response::json($result[0],$result[1]);
    }
}
