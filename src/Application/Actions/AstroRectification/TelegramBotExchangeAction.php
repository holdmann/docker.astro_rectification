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
        try
        {
            $data = $this->request->getParsedBody();

            $this->logger->info('request data', $data);

            $json = json_encode(['1' => '2'], JSON_PRETTY_PRINT);
            $this->response->getBody()->write($json);

            return $this->response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
        }
        catch (\Throwable $exception)
        {
            $this->logger->error($exception->getMessage(), [
                'trace' => $exception->getTraceAsString()
            ]);

            $json = json_encode(['error' => $exception->getMessage()], JSON_PRETTY_PRINT);
            $this->response->getBody()->write($json);

            return $this->response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }
}
