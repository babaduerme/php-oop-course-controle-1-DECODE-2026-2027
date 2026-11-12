<?php

namespace App\lib\Controllers;

use App\lib\Http\Response;
use App\lib\Http\Request;

abstract class AbstractController
{
    public abstract function process(Request $request): Response;

    public function methjson($data)
    {
        return json_encode($data);
    }
}
