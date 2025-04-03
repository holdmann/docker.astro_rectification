<?php

declare(strict_types=1);

namespace App\Application\Actions\AstroRectification;

use Psr\Http\Message\ResponseInterface as Response;
use App\Application\Actions\Action;
use Telegram\Bot\Api;
use App\Application\Settings\SettingsInterface;

class TelegramBotExchangeAction extends Action
{
    /**
     * {@inheritdoc}
     */
    protected function action(): Response
    {
        try {
            $data = $this->request->getParsedBody();

            $this->logger->info('request data', $data ?? []);

            if (!isset($data['message']['text'])) {
                throw new \Exception('Wrong data');
            }

            $chatId = (int) $data['message']['from']['id'];
            $text = trim($data['message']['text']);

            if ($text === '/help') {
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
        } catch (\Throwable $exception) {
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

    /**
     * @param int $chatId
     * @param string $text
     * @param string $reply_markup
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Telegram\Bot\Exceptions\TelegramSDKException
     */
    protected function sendResponseToBot(int $chatId, string $text, string $reply_markup = '')
    {
        $settings = $this->container->get(SettingsInterface::class);
        $token = $settings->get('telegramBotToken');//'7959424182:AAH2t0vjgZRRlPHDkXp-95_j-LkRUzB3zTU';

        $client = new Api($token);
        $response = $client->sendMessage([
            'chat_id' => $chatId,
            'parse_mode' => 'HTML',
            'text' => $text,
            'reply_markup' => $reply_markup,
        ]);
    }
}
