<?php

namespace Productsup\GuzzleReactBridge;

use Closure;
use GuzzleHttp\Promise\Promise;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;

function run(callable $action)
{
    $loop = Factory::create();

    \GuzzleHttp\Promise\queue(new ReactTaskQueue($loop));

    $loop->nextTick($action);

    $loop->run();
}

/**
 * "Concurrent sleep" using a timer.
 *
 * @param LoopInterface $loop
 *
 * @return Closure
 */
function sleep_fn(LoopInterface $loop)
{
    return function ($interval) use ($loop) {
        $promise = new Promise();

        $loop->addTimer($interval, function () use ($promise) {
            $promise->resolve(null);
        });

        return $promise;
    };
}
