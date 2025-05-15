<?php

declare(strict_types=1);

namespace App\Application\Actions\AstroRectification;

use Psr\Http\Message\ResponseInterface as Response;
use App\Application\Actions\Action;
use Telegram\Bot\Api;
use App\Application\Settings\SettingsInterface;
use PDO;

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
/start - начать
/about - о нас
', $firstName);
                $this->sendResponseToBot($chatId, $text_return);
            } else {

                $isStartMessage = ($text === '/start');

                $userId = $data['message']['from']['id'];
                $surveyId = 1;

                $db = new PDO('mysql:host=localhost;dbname=dkintevt_astro2', 'dkintevt_astro2', 'aQ9&RmZJqFR1');

                $responseId = $this->getSurveyResponseIdByUserId($db, $userId);
                if (null === $responseId)
                {
                    // this means user not yet starting fill survey
                    $responseId = $this->addSurveyResponse($db, $surveyId, $userId);
                }

                $questions = $this->getQuestions($db, $surveyId);
                $answers = $this->getAnswers($db, $responseId);

                $question = $this->getNextNotAnsweredQuestion($questions, $answers);
                if (null !== $question) {
                    if (!$isStartMessage)
                    {
                        $this->addAnswer($db, $surveyId, $question['id'], $text);
                    } else {
                        $this->sendResponseToBot($chatId, $question['question']);
                    }
                } else {
                    // this means all question already answered.
                    $this->sendResponseToBot($chatId, 'ТЫ ВСЕ ЗАПОЛНИЛ, ИДИ С БОГОМ');
                }
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

    /**
     * @param PDO $db
     * @param int $surveyId
     * @return array
     */
    protected function getQuestions(PDO $db, int $surveyId): array
    {
        $questions = [];

        $sql = sprintf('select * from `question` where `survey_id` = %d order by `question_order` asc', $surveyId);
        $iterator = $db->query($sql);
        while($row = $iterator->fetch()) {
            $id = $row['id'];

            $questions[$id] = [
                'id' => $id,
                'type' => $row['question_type'],
                'question' => $row['question_text'],
                'is_required' => $row['is_required'],
                'sort' => $row['question_order'],
            ];
        }

        return $questions;
    }

    /**
     * @param PDO $db
     * @param int $surveyResponseId
     * @return array
     */
    protected function getAnswers(PDO $db, int $surveyResponseId): array
    {
        $answers = [];

        $sql = sprintf(
            'select * from `survey_answer` where `survey_response_id` = %d AND CHAR_LENGTH(`answer_value`) > 0',
            $surveyResponseId
        );
        $iterator = $db->query($sql);
        while($row = $iterator->fetch()) {
            $id = $row['question_id'];

            $answers[$id] = [
                'id' => $id,
                'answer' => $row['answer_value'],
            ];
        }

        return $answers;
    }

    /**
     * @param array $questions
     * @param array $answers
     * @return array|null
     */
    protected function getNextNotAnsweredQuestion(array $questions, array $answers): ?array
    {
        foreach ($questions as $questionId => $question) {
            if (!isset($answers[$questionId])) {
                return $question;
            }
        }

        return null;
    }

    /**
     * @param PDO $db
     * @param $responseId
     * @param $questionId
     * @param $answer
     * @return void
     */
    protected function addAnswer(PDO $db, $responseId, $questionId, $answer): void
    {
        $sql = sprintf(
            'insert into `survey_answer` (`survey_response_id`, `question_id`, `answer_value`) values (%d, %d, "%s")',
            $responseId,
            $questionId,
            $answer
        );
        $db->exec($sql);
    }

    /**
     * @param PDO $db
     * @param int $userId
     * @return int|null
     */
    protected function getSurveyResponseIdByUserId(PDO $db, int $userId): ?int
    {
        $sql = sprintf('select `survey_response_id` from `survey_response_user` where `user_id` = %d', $userId);

        $row = $db->query($sql)->fetch();
        if (isset($row['survey_response_id'])) {
            return (int) $row['survey_response_id'];
        }

        return null;
    }

    /**
     * @param PDO $db
     * @param int $surveyId
     * @param int $userId
     * @return int
     */
    protected function addSurveyResponse(PDO $db, int $surveyId, int $userId): int
    {
        try
        {
            $db->beginTransaction();
            $sql = sprintf(
                'insert into `survey_response` (`survey_id`, `time_taken`) values (%d, "%s")',
                $surveyId,
                date('Y-m-d H:i:s')
            );
            $db->exec($sql);

            $surveyResponseId = (int) $db->lastInsertId();

            $sql = sprintf(
                'insert into `survey_response_user` (`survey_response_id`, `user_id`) values (%d, %d)',
                $surveyResponseId,
                $userId
            );
            $db->exec($sql);

            $db->commit();

            return $surveyResponseId;
        } catch (\Throwable $exception) {
            $db->rollBack();

            throw $exception;
        }
    }
}
