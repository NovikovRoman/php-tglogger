# TgLogger

> Logging in telegram chats, channels.

## Example

```php
<?php

use TgLogger\TgLogger;

require_once __DIR__ . '/../vendor/autoload.php';

$chatId = -100…92;
$botToken = '528…74:AA…0s';

$log = new TgLogger($botToken, $chatId, 'project name [optional]');

try {
    $log->Error('test error', ['id' => 4, 'firstName' => 'Roman', 'lastName' => 'Novikov']);

} catch (Exception $e) {
    // to the standard log
    error_log($e->getMessage() .':: '. $log->getMessage());
}
```