<?php
/**
 * /local/php_interface/init.php
 *
 * Точка входа для кастомизации Bitrix.
 * Подключаем обработчики событий.
 */

// Обработчик синхронизации заказов с Laravel API
require_once __DIR__ . '/handlers/order_sync.php';
