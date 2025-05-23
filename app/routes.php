<?php

declare(strict_types=1);

use App\Application\Actions\AstroRectification\TelegramBotExchangeAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app) {
    //$app->options('/{routes:.*}', function (Request $request, Response $response) {
        // CORS Pre-Flight OPTIONS Request Handler
    //    return $response;
    //});

    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write('Hello world!');
        return $response;
    });

    $app->group('/api/v1/astro-rectification/', function (Group $group) {
        /* $group->any('', function (Request $request, Response $response) {
            var_dump($request->getParsedBody());
            $response->getBody()->write('Hello world 2!');
            return $response;
        }); */

        $group->any('', TelegramBotExchangeAction::class);
    });
};
