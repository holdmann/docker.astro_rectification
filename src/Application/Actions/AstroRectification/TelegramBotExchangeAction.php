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

            if (!isset($data['message']['text']))
            {
                throw new \Exception('Wrong data');
            }

            $chatId = (int) $data['message']['from']['id'];
            $text = trim($data['message']['text']);

            if ($text === '/help')
            {
                $firstName = $data['message']['from']['first_name'] ?? 'Странник';

                $text_return = sprintf('Привет, %s, вот команды, что я понимаю: 
/help - список команд
/about - о нас
', $firstName);
                $this->sendResponseToBot($chatId, $text_return);
            }

            $json = json_encode(['success' => true], JSON_PRETTY_PRINT);
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

            $json = json_encode(['success' => false, 'error' => $exception->getMessage()], JSON_PRETTY_PRINT);
            $this->response->getBody()->write($json);

            return $this->response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }

    protected function sendResponseToBot(int $chatId, string $text, string $reply_markup = '')
    {
        $token =
        $ch = curl_init();
        $ch_post = [
            CURLOPT_URL => 'https://api.telegram.org/bot' . $bot_token . '/sendMessage',
            CURLOPT_POST => TRUE,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_POSTFIELDS => [
                'chat_id' => $chatId,
                'parse_mode' => 'HTML',
                'text' => $text,
                'reply_markup' => $reply_markup,
            ]
        ];

        curl_setopt_array($ch, $ch_post);
        curl_exec($ch);
    }
}
