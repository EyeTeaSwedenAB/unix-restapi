<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../ApacheConfig.php';

$env = parse_ini_file(__DIR__ . '/../.env');

$app = AppFactory::create();

$errorMiddleware = $app->addErrorMiddleware(true, true, true);

$app->get('/vhosts', function (Request $request, Response $response, $args) use ($env) {
	$apacheConfig = new ApacheConfig($env["APACHE_CONFIG_FILE"]);
	$virtualHosts = $apacheConfig->getVirtualHosts();

	$response->getBody()->write(json_encode($virtualHosts));
	return $response
		->withHeader('Content-Type', 'application/json');
});

$app->run();
