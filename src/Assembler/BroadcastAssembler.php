<?php
declare(strict_types=1);

namespace Crustum\Viator\Assembler;

use Cake\Collection\CollectionInterface;
use Crustum\Viator\Converter\BroadcastChannels;
use Crustum\Viator\Converter\BroadcastEvents;
use Crustum\Viator\Result\Result;

/**
 * Broadcast file assembly for the runtime dir.
 *
 * Events and channels render from the walk components.
 */
final class BroadcastAssembler
{
    /**
     * @param \Cake\Collection\CollectionInterface<array-key, \Crustum\Scrutator\Component\BroadcastEvent>|null $events Event components from the walk, null when skipped
     * @param \Cake\Collection\CollectionInterface<array-key, \Crustum\Scrutator\Component\BroadcastChannel>|null $channels Channel components from the walk, null when skipped or unresolvable
     * @param string $runtimeDir Output subdirectory for the runtime
     * @return array<int, \Crustum\Viator\Result\Result>
     */
    public function assemble(?CollectionInterface $events, ?CollectionInterface $channels, string $runtimeDir): array
    {
        $results = [];
        if ($events instanceof CollectionInterface) {
            foreach ((new BroadcastEvents())->convertComponents($events, ROOT) as $result) {
                $results[] = new Result($runtimeDir . '/' . $result->name, $result->content);
            }
        }

        if ($channels instanceof CollectionInterface) {
            $converted = (new BroadcastChannels())->convert($channels);
            if ($converted instanceof Result) {
                $results[] = new Result($runtimeDir . '/' . $converted->name, $converted->content);
            }
        }

        return $results;
    }
}
