<?php

namespace App\Controllers\Users;

use App\lib\Controllers\AbstractController;
use App\lib\Http\Request;
use App\lib\Http\Response;

class GetUserController extends AbstractController
{
    public function process(Request $request): Response
    {
        $response = new Response();
        $response->statusCode = 200;
        $response->content = json_encode([
            'id' => $request->getRouteParam('id'),
        ], JSON_THROW_ON_ERROR);

        return $response;
    }
}
