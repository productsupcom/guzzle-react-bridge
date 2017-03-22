<?php

namespace Productsup\GuzzleReactBridge;

use Closure;
use GuzzleHttp\Promise\Promise;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use function GuzzleHttp\Promise\coroutine;

/**
 * Run an action inside a fresh event loop
 *
 * Shortcut to create an event loop with default settings, that suits for most cases.
 *
 * @param callable $action
 *
 * @return void
 */
function run(callable $action)
{
    $loop = Factory::create();

    \GuzzleHttp\Promise\queue(new ReactTaskQueue($loop));

    $loop->nextTick($action);

    $loop->run();
}

/**
 * Run a coroutine inside a fresh event loop
 *
 * Shortcut to create an event loop with default settings, that suits for most cases.
 *
 * @param callable $coroutine
 *
 * @return mixed
 */
function run_coroutine_fn(callable $coroutine)
{
    $loop = Factory::create();

    $coroutineFn = function () use ($loop, $coroutine) {
        return $coroutine($loop);
    };

    \GuzzleHttp\Promise\queue(new ReactTaskQueue($loop));

    $globalResult = null;
    /** @var \Exception $globalError */
    $globalError = null;

    $loop->nextTick(function () use ($coroutineFn, &$globalResult, &$globalError) {
        $coroutineInvocation = coroutine($coroutineFn)
            ->then(function ($result) use (&$globalResult) {
                return $globalResult = $result;
            })
            ->otherwise(function ($reason) use (&$globalError) {
                $globalError = \GuzzleHttp\Promise\exception_for($reason);

                // Reject it again, don't change the state.
                return \GuzzleHttp\Promise\rejection_for($reason);
            })
        ;

        // Here we are, waiting for the coroutine (promise) to complete.
    });

    $loop->run();

    // And check whether there is an exception or not...
    if ($globalError) {
        throw $globalError;
    }

    // TODO Support it.
    return $globalResult;
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
