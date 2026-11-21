<?php
declare(strict_types=1);

namespace TestApp\Enum;

/**
 * Notification channel.
 */
enum NotificationChannel: string
{
    case Mail = 'mail';
    case Database = 'database';
}
