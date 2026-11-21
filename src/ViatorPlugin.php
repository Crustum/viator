<?php
declare(strict_types=1);

namespace Crustum\Viator;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\RouteBuilder;
use Crustum\Viator\Command\RoutesGenerateCommand;
use Override;

/**
 * Plugin for Crustum/Viator
 */
class ViatorPlugin extends BasePlugin
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);

        if (!Configure::check('Viator')) {
            if (file_exists(CONFIG . 'viator.php')) {
                Configure::load('viator', 'default');
            } elseif (file_exists($this->getConfigPath() . 'viator.php')) {
                Configure::load('Crustum/Viator.viator', 'default', false);
            }
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function routes(RouteBuilder $routes): void
    {
        parent::routes($routes);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        return $middlewareQueue;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function console(CommandCollection $commands): CommandCollection
    {
        $commands = parent::console($commands);

        $commands->add(RoutesGenerateCommand::defaultName(), RoutesGenerateCommand::class);

        return $commands;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function services(ContainerInterface $container): void
    {
    }
}
