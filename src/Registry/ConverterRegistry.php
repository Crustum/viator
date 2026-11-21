<?php
declare(strict_types=1);

namespace Crustum\Viator\Registry;

use Cake\Core\ContainerInterface;
use Crustum\Investigator\Type\Contract\Type;
use InvalidArgumentException;

/**
 * Resolves converters by format and runs them.
 *
 * Converters are read from the Cake container when it knows them and built
 * directly otherwise; either way they must satisfy the converter contract.
 */
class ConverterRegistry
{
    /**
     * @var array<string, \Crustum\Viator\Registry\ConverterInterface>
     */
    protected array $converters = [];

    /**
     * @param \Cake\Core\ContainerInterface|null $container Container resolving converters
     */
    public function __construct(protected ?ContainerInterface $container = null)
    {
    }

    /**
     * Register a converter class.
     *
     * @param class-string<\Crustum\Viator\Registry\ConverterInterface> $converter Converter class
     * @return void
     */
    public function register(string $converter): void
    {
        if ($this->hasConverter($converter)) {
            return;
        }

        if ($this->container instanceof ContainerInterface && $this->container->has($converter)) {
            $instance = $this->container->get($converter);
        } else {
            $instance = new $converter();
        }

        if (!($instance instanceof ConverterInterface)) {
            throw new InvalidArgumentException("Converter {$converter} must implement " . ConverterInterface::class);
        }

        $this->converters[$converter] = $instance;
    }

    /**
     * Convert an analyzed type with a registered converter.
     *
     * @param \Crustum\Investigator\Type\Contract\Type $result Analyzed type
     * @param class-string<\Crustum\Viator\Registry\ConverterInterface> $converter Converter class
     * @return string TypeScript type
     */
    public function convert(Type $result, string $converter): string
    {
        if (!$this->hasConverter($converter)) {
            throw new InvalidArgumentException("No converter registered for format: {$converter}");
        }

        return $this->converters[$converter]->convert($result);
    }

    /**
     * Whether a converter is registered.
     *
     * @param class-string<\Crustum\Viator\Registry\ConverterInterface> $format Converter class
     * @return bool
     */
    public function hasConverter(string $format): bool
    {
        return isset($this->converters[$format]);
    }
}
