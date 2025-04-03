<?php

declare(strict_types=1);

namespace App\Application\Actions\AstroRectification;

use Psr\Http\Message\ResponseInterface as Response;
use App\Application\Actions\Action;

class TelegramBotExchangeAction extends Action
{
    /**
     * {@inheritdoc}
     */
    protected function action(): Response
    {
        $this->logger->info('request data', $this->request->getParsedBody());

        $json = json_encode(['1' => '2'], JSON_PRETTY_PRINT);
        $this->response->getBody()->write($json);

        return $this->response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(200);
    }
}
