<?php
declare(strict_types=1);

/*
 * Regenerate the TypeScript fixtures the vitest suite imports.
 *
 * Every `vitest run` starts from freshly generated `tests/js/generated`
 * output, so the JS tests always assert the current generator behavior.
 */

require dirname(__DIR__, 2) . '/tests/bootstrap.php';

use Cake\Console\CommandRunner;
use Cake\Core\Configure;
use Cake\Routing\Router;
use TestApp\Application;

$out = $argv[1] ?? dirname(__DIR__, 2) . '/tests/js/generated';

Router::reload();

$app = new Application(CONFIG);

Configure::write('Viator.ignoreActions', ['fetchCollection']);
Configure::write('Viator.enumMethods', true);
// Mirror the workbench default: retired source providers stay hidden.
Configure::write('Viator.testHideRetiredSourceProvider', true);
Configure::write('Viator.envFiles', [CONFIG . '.env']);

$runner = new CommandRunner($app);
$exit = $runner->run(['cake', 'viator', 'routes', '--path=' . $out, '--with-form']);

exit($exit === 0 ? 0 : 1);
