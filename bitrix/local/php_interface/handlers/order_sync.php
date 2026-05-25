<?php
/**
 * Обработчик события сохранения заказа.
 *
 * Подключение: в файле /local/php_interface/init.php
 * добавить строку:
 *     require_once __DIR__ . '/handlers/order_sync.php';
 *
 * Используем D7-событие OnSaleOrderSaved, которое срабатывает
 * после успешного сохранения заказа в БД Bitrix.
 */

use Bitrix\Main\EventManager;
use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Web\HttpClient;

EventManager::getInstance()->addEventHandler(
    'sale',
    'OnSaleOrderSaved',
    'onOrderSavedHandler'
);

/**
 * Обработчик события OnSaleOrderSaved.
 *
 * @param \Bitrix\Main\Event $event
 */
function onOrderSavedHandler(\Bitrix\Main\Event $event): void
{
    /** @var \Bitrix\Sale\Order $order */
    $order = $event->getParameter('ENTITY');

    if (!$order) {
        return;
    }

    // Собираем данные заказа
    $orderData = prepareOrderPayload($order);

    // Пробуем отправить синхронно — если не прошло, ставим в очередь
    $sent = sendOrderToApi($orderData);

    if (!$sent) {
        enqueueFailedOrder($orderData);
    }
}

/**
 * Формирует payload заказа для отправки в API.
 */
function prepareOrderPayload(\Bitrix\Sale\Order $order): array
{
    $items = [];

    /** @var \Bitrix\Sale\Basket $basket */
    $basket = $order->getBasket();

    if ($basket) {
        /** @var \Bitrix\Sale\BasketItem $basketItem */
        foreach ($basket->getBasketItems() as $basketItem) {
            $items[] = [
                'product_id' => $basketItem->getProductId(),
                'name'       => $basketItem->getField('NAME'),
                'quantity'   => $basketItem->getQuantity(),
                'price'      => $basketItem->getPrice(),
            ];
        }
    }

    return [
        'order_id' => $order->getId(),
        'status'   => $order->getField('STATUS_ID'),
        'total'    => $order->getPrice(),
        'currency' => $order->getCurrency(),
        'items'    => $items,
        'created'  => $order->getDateInsert()->toString(),
    ];
}

/**
 * Отправляет данные заказа в Laravel API.
 *
 * Критически важно: при любой ошибке возвращаем false,
 * но НЕ бросаем исключение — заказ должен сохраниться.
 */
function sendOrderToApi(array $orderData): bool
{
    // URL внешнего сервиса — вынести в настройки (.settings.php)
    $apiUrl = \Bitrix\Main\Config\Option::get(
        'main',
        'laravel_api_url',
        'https://api.example.com'
    );

    try {
        $httpClient = new HttpClient([
            'socketTimeout' => 5,   // не висим дольше 5 секунд
            'streamTimeout' => 5,
            'redirect'      => false,
        ]);

        $httpClient->setHeader('Content-Type', 'application/json');
        $httpClient->setHeader('Accept', 'application/json');

        $response = $httpClient->post(
            $apiUrl . '/api/orders',
            json_encode($orderData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
        );

        $status = $httpClient->getStatus();

        if ($status >= 200 && $status < 300) {
            return true;
        }

        Debug::writeToFile(
            "Order sync HTTP error: status={$status}, order_id={$orderData['order_id']}",
            '',
            '/var/log/bitrix/order_sync.log'
        );

        return false;

    } catch (\Throwable $e) {
        // Логируем, но НЕ прерываем сохранение заказа
        Debug::writeToFile(
            "Order sync exception: {$e->getMessage()}, order_id={$orderData['order_id']}",
            '',
            '/var/log/bitrix/order_sync.log'
        );

        return false;
    }
}

/**
 * Ставит неотправленный заказ в очередь для повторной отправки.
 *
 * Используем Highload-блок или простую таблицу в БД.
 * Здесь — вариант с записью в опцию для простоты.
 * В продакшене лучше отдельная таблица order_sync_queue.
 */
function enqueueFailedOrder(array $orderData): void
{
    try {
        // Сохраняем в таблицу очереди через D7
        $connection = \Bitrix\Main\Application::getConnection();
        $sqlHelper  = $connection->getSqlHelper();

        $connection->queryExecute(sprintf(
            "INSERT INTO order_sync_queue (order_id, payload, attempts, created_at)
             VALUES (%d, '%s', 0, NOW())
             ON DUPLICATE KEY UPDATE payload = VALUES(payload), attempts = 0",
            (int) $orderData['order_id'],
            $sqlHelper->forSql(json_encode($orderData, JSON_UNESCAPED_UNICODE))
        ));

        Debug::writeToFile(
            "Order {$orderData['order_id']} enqueued for retry",
            '',
            '/var/log/bitrix/order_sync.log'
        );

    } catch (\Throwable $e) {
        Debug::writeToFile(
            "Failed to enqueue order: {$e->getMessage()}",
            '',
            '/var/log/bitrix/order_sync.log'
        );
    }
}
