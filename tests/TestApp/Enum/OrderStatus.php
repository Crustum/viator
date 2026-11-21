<?php
declare(strict_types=1);

namespace TestApp\Enum;

use Crustum\Viator\Attribute\ViatorIgnore;
use RuntimeException;
use stdClass;
use Stringable;
use TestApp\Enum\Concerns\HasIconTrait;

/**
 * Order status with resolvable methods.
 */
enum OrderStatus: string
{
    use HasIconTrait;

    case Pending = 'pending';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /**
     * Human label.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting Payment',
            self::Shipped => 'On Its Way',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Display color.
     *
     * @return string
     */
    public function color(): string
    {
        return match ($this) {
            self::Delivered => 'green',
            self::Cancelled => 'red',
            default => 'gray',
        };
    }

    /**
     * Optional description.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return match ($this) {
            self::Cancelled => 'The customer said "no thanks" — see /docs/refunds.',
            default => null,
        };
    }

    /**
     * Whether the order is final.
     *
     * @return bool
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Delivered, self::Cancelled], true);
    }

    /**
     * Shipping weight.
     *
     * @return int
     */
    public function weight(): int
    {
        return match ($this) {
            self::Pending => 1,
            self::Shipped => 2,
            self::Delivered => 3,
            self::Cancelled => 4,
        };
    }

    /**
     * Tag list.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['order', $this->value];
    }

    /**
     * Badge shape.
     *
     * @return array<string, string>
     */
    public function badge(): array
    {
        return ['tone' => $this->color(), 'text' => $this->label()];
    }

    /**
     * Notification channel.
     *
     * @return \TestApp\Enum\NotificationChannel
     */
    public function channel(): NotificationChannel
    {
        return $this->isFinal() ? NotificationChannel::Mail : NotificationChannel::Database;
    }

    /**
     * Fulfilment steps.
     *
     * @return array<int, string>
     */
    public function steps(): array
    {
        return ['placed', $this->value];
    }

    /**
     * Uppercase label.
     *
     * @return \Stringable
     */
    public function heading(): Stringable
    {
        $label = $this->label();

        return new class ($label) implements Stringable {
            /**
             * @param string $label Label
             */
            public function __construct(protected string $label)
            {
            }

            /**
             * @return string
             */
            public function __toString(): string
            {
                return strtoupper($this->label);
            }
        };
    }

    /**
     * Tracking URL, unavailable while pending.
     *
     * @return string
     */
    public function trackingUrl(): string
    {
        return match ($this) {
            self::Shipped => 'https://example.com/track/shipped',
            self::Delivered => 'https://example.com/track/delivered',
            self::Cancelled => 'https://example.com/track/cancelled',
        };
    }

    /**
     * Never resolvable helper.
     *
     * @return string
     */
    public function unavailable(): string
    {
        throw new RuntimeException('Not resolvable at generation time.');
    }

    /**
     * Unrepresentable payload shape.
     *
     * @return \stdClass
     */
    public function payload(): stdClass
    {
        return new stdClass();
    }

    /**
     * Order slug.
     *
     * @param string $prefix Prefix
     * @return string
     */
    public function slug(string $prefix = 'order'): string
    {
        return $prefix . '-' . $this->value;
    }

    /**
     * Identity check.
     *
     * @param \TestApp\Enum\OrderStatus $other Other status
     * @return bool
     */
    public function is(OrderStatus $other): bool
    {
        return $this === $other;
    }

    /**
     * Void helper.
     *
     * @return void
     */
    public function touch(): void
    {
    }

    /**
     * Static helper.
     *
     * @return self
     */
    public static function fallback(): self
    {
        return self::Pending;
    }

    /**
     * Internal code, hidden from generation.
     *
     * @return string
     */
    #[ViatorIgnore]
    public function internalCode(): string
    {
        return 'code-' . $this->value;
    }

    /**
     * Note, hidden unless its flag holds.
     *
     * @return string
     */
    #[ViatorIgnore(unless: 'Viator.testExposeOrderNotes')]
    public function note(): string
    {
        return 'note-' . $this->value;
    }

    /**
     * Protected helper.
     *
     * @return string
     */
    protected function internalName(): string
    {
        return 'internal-' . $this->value;
    }

    /**
     * Private helper.
     *
     * @return string
     */
    private function secretName(): string
    {
        return 'secret-' . $this->value;
    }
}
