<?php
declare(strict_types=1);

namespace TestApp\Event;

use Crustum\Broadcasting\Channel\Channel;
use Crustum\Broadcasting\Event\BroadcastableInterface;

/**
 * Order-shipped broadcast fixture.
 */
class OrderShipped implements BroadcastableInterface
{
    /**
     * @param int $orderId Order id
     * @param string $trackingNumber Tracking number
     * @param string $carrier Carrier name
     */
    public function __construct(
        public int $orderId,
        public string $trackingNumber,
        public string $carrier,
    ) {
    }

    /**
     * Event name.
     *
     * @return string
     */
    public function broadcastEvent(): string
    {
        return static::class;
    }

    /**
     * Broadcast channel.
     *
     * @return \Crustum\Broadcasting\Channel\Channel|array<\Crustum\Broadcasting\Channel\Channel>
     */
    public function broadcastChannel(): Channel|array
    {
        return new Channel('orders.' . $this->orderId);
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
            'orderId' => $this->orderId,
            'trackingNumber' => $this->trackingNumber,
            'carrier' => $this->carrier,
        ];
    }
}
