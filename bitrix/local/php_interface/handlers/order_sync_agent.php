<?php
/**
 * Агент для повторной отправки заказов из очереди.
 *
 * Регистрация агента (выполнить один раз в админке или через CLI):
 *
 *   \CAgent::AddAgent(
 *       "\\App\\Agents\\OrderSyncAgent::run();",
 *       "",
 *       "N",           // не критичный
 *       300,           // интервал 5 минут
 *       "",
 *       "Y",           // активен
 *       ""
 *   );
 *
 * Как работает фоновая отправка:
 * ────────────────────────────────────────────────────────────
 * 1. При сохранении заказа обработчик пытается отправить данные синхронно.
 * 2. Если API недоступен — заказ попадает в таблицу order_sync_queue.
 * 3. Агент (этот файл) запускается каждые 5 минут по cron/хитам,
 *    выбирает до 50 необработанных записей и повторяет отправку.
 * 4. После 5 неудачных попыток запись помечается как failed.
 *
 * Альтернативы:
 * — \Bitrix\Main\Application::getInstance()->addBackgroundJob()
 *   для немедленной фоновой отправки (работает в рамках хита);
 * — RabbitMQ / Redis очередь, если нагрузка на заказы высокая;
 * — Cron-скрипт вместо агента для большей предсказуемости.
 * ────────────────────────────────────────────────────────────
 */

namespace App\Agents;

use Bitrix\Main\Application;
use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Web\HttpClient;

class OrderSyncAgent
{
    private const MAX_ATTEMPTS  = 5;
    private const BATCH_SIZE    = 50;
    private const API_TIMEOUT   = 5;

    /**
     * Точка входа агента. Возвращает строку вызова,
     * чтобы Bitrix перезапустил агент.
     */
    public static function run(): string
    {
        try {
            self::processQueue();
        } catch (\Throwable $e) {
            Debug::writeToFile(
                "OrderSyncAgent error: {$e->getMessage()}",
                '',
                '/var/log/bitrix/order_sync.log'
            );
        }

        // Возвращаем вызов, чтобы агент перезапускался
        return "\\App\\Agents\\OrderSyncAgent::run();";
    }

    private static function processQueue(): void
    {
        $connection = Application::getConnection();

        // Выбираем заказы с attempts < MAX и статусом pending
        $result = $connection->query(sprintf(
            "SELECT id, order_id, payload, attempts
             FROM order_sync_queue
             WHERE attempts < %d
             ORDER BY created_at ASC
             LIMIT %d",
            self::MAX_ATTEMPTS,
            self::BATCH_SIZE
        ));

        $apiUrl = \Bitrix\Main\Config\Option::get(
            'main',
            'laravel_api_url',
            'https://api.example.com'
        );

        while ($row = $result->fetch()) {
            $orderData = json_decode($row['payload'], true);
            $sent      = self::sendToApi($apiUrl, $orderData);

            if ($sent) {
                // Успешно — удаляем из очереди
                $connection->queryExecute(
                    "DELETE FROM order_sync_queue WHERE id = " . (int) $row['id']
                );
            } else {
                // Увеличиваем счётчик попыток
                $connection->queryExecute(sprintf(
                    "UPDATE order_sync_queue SET attempts = attempts + 1 WHERE id = %d",
                    (int) $row['id']
                ));
            }
        }
    }

    private static function sendToApi(string $apiUrl, array $data): bool
    {
        try {
            $http = new HttpClient([
                'socketTimeout' => self::API_TIMEOUT,
                'streamTimeout' => self::API_TIMEOUT,
            ]);

            $http->setHeader('Content-Type', 'application/json');
            $http->setHeader('Accept', 'application/json');

            $http->post(
                $apiUrl . '/api/orders',
                json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
            );

            $status = $http->getStatus();

            return $status >= 200 && $status < 300;

        } catch (\Throwable $e) {
            Debug::writeToFile(
                "Agent send error: {$e->getMessage()}, order_id={$data['order_id']}",
                '',
                '/var/log/bitrix/order_sync.log'
            );
            return false;
        }
    }
}
