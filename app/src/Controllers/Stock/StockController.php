<?php

namespace App\Controllers\Stock;

use App\lib\Controllers\AbstractController;
use App\lib\Http\Request;
use App\lib\Http\Response;

class StockController extends AbstractController
{
    public function process(Request $request): Response
    {
        $response = new Response();
        $response->statusCode = 200;
        $response->content = json_encode([
            'message' => 'Liste du stock',
            'items' => [],
        ], JSON_THROW_ON_ERROR);

        return $response;
    }
}
