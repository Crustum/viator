<?php
declare(strict_types=1);

namespace TestApp\Event;

use Crustum\Broadcasting\Channel\Channel;
use Crustum\Broadcasting\Channel\PrivateChannel;
use Crustum\Broadcasting\Event\BroadcastableInterface;

/**
 * User-notification broadcast fixture.
 */
class UserNotification implements BroadcastableInterface
{
    /**
     * @param int $userId User id
     * @param string $title Title
     * @param string $message Message
     */
    public function __construct(
        public int $userId,
        public string $title,
        public string $message,
    ) {
    }

    /**
     * Event name.
     *
     * @return string
     */
    public function broadcastEvent(): string
    {
        return 'user.notification';
    }

    /**
     * Broadcast channel.
     *
     * @return \Crustum\Broadcasting\Channel\Channel|array<\Crustum\Broadcasting\Channel\Channel>
     */
    public function broadcastChannel(): Channel|array
    {
        return new PrivateChannel('user.' . $this->userId . '.notifications');
    }

    /**
     * Socket id to exclude.
     *
     * @return string|null
     */
    public function broadcastSocket(): ?string
    {
        return null;
    }

    /**
     * Broadcast payload.
     *
     * @return array<string, mixed>|null
     */
    public function broadcastData(): ?array
    {
        return [
            'userId' => $this->userId,
            'title' => $this->title,
            'message' => $this->message,
        ];
    }
}
