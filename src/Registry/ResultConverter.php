<?php
declare(strict_types=1);

namespace Crustum\Viator\Registry;

use Cake\Core\ContainerInterface;
use Crustum\Investigator\Type\Contract\Type;

/**
 * Static entry point to the converter registry.
 */
class ResultConverter
{
    /**
     * @var \Crustum\Viator\Registry\ConverterRegistry|null
     */
    protected static ?ConverterRegistry $registry = null;

    /**
     * @var \Cake\Core\ContainerInterface|null
     */
    protected static ?ContainerInterface $container = null;

    /**
     * Hand the registry the container converters resolve from.
     *
     * @param \Cake\Core\ContainerInterface $container Container
     * @return void
     */
    public static function setContainer(ContainerInterface $container): void
    {
        static::$container = $container;
        static::$registry = null;
    }

    /**
     * Read the shared registry.
     *
     * @return \Crustum\Viator\Registry\ConverterRegistry
     */
    public static function getRegistry(): ConverterRegistry
    {
        static::$registry ??= new ConverterRegistry(static::$container);

        return static::$registry;
    }

    /**
     * Convert an analyzed type with a registered converter.
     *
     * @param \Crustum\Investigator\Type\Contract\Type $result Analyzed type
     * @param class-string<\Crustum\Viator\Registry\ConverterInterface> $converter Converter class
     * @return string TypeScript type
     */
    public static function to(Type $result, string $converter): string
    {
        return static::getRegistry()->convert($result, $converter);
    }

    /**
     * Register a converter class.
     *
     * @param class-string<\Crustum\Viator\Registry\ConverterInterface> $converter Converter class
     * @return void
     */
    public static function register(string $converter): void
    {
        static::getRegistry()->register($converter);
    }
}
