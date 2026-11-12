<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\lib\Http\Router;
use App\lib\Http\Request;

$request = new Request();

$router = new Router();
$router->main($request);
