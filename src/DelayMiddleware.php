<?php

namespace Productsup\GuzzleReactBridge;

use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use React\EventLoop\LoopInterface;

class DelayMiddleware
{
    /** @var LoopInterface */
    private $loop;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function __invoke($handler)
    {
        return function ($request, $options) use ($handler) {
            if (array_key_exists('delay', $options) && $options['delay']) {
                $promise = new Promise();

                // Setup a timer for this request...
                $this->loop->addTimer($options['delay'], function () use ($handler, $request, $options, $promise) {
                    /** @var PromiseInterface $realPromise */
                    $realPromise = $handler($request, $options);

                    $realPromise->then(
                        function ($result) use ($promise) {
                            $promise->resolve($result);
                        },
                        function ($reason) use ($promise) {
                            $promise->reject($reason);
                        }
                    );
                });

                return $promise;
            } else {
                // Straight return.
                return $handler($request, $options);
            }
        };
    }
}
