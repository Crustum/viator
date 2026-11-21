<?php
declare(strict_types=1);

/**
 * TestApp channel authorization configuration.
 *
 * Mirrors the upstream workbench `routes/channels.php`: parameterized
 * channels, a plain channel, and a punctuation-heavy name that exercises
 * quoting in the generated TypeScript.
 */

use Crustum\Broadcasting\Broadcasting;

Broadcasting::channel('orders.{orderId}', fn($user, $orderId): true => true);

Broadcasting::channel('user.{userId}.notifications', function ($user, $userId): bool {
    if (!$user) {
        return false;
    }

    return (int)$user->id === (int)$userId;
});

Broadcasting::channel('chat.{roomId}.messages', fn($user, $roomId): true => true);

Broadcasting::channel('public-announcements', fn($user): true => true);

Broadcasting::channel('quirky.`tick`-$dollar.{roomId}', fn($user, $roomId): true => true);
