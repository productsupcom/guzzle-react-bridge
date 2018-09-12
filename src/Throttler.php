<?php

namespace Productsup\GuzzleReactBridge;

use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

/**
 * Abstract throttler for any async actions (that produce promises)
 *
 * Can be used as a Guzzle middleware (see guzzleMiddleware() method).
 */
class Throttler
{
    /** @var LoopInterface */
    private $loop;

    private $allowedAmount;

    /**
     * Period in seconds.
     *
     * @var int
     */
    private $perPeriod;

    /**
     * Currently active call, to properly stop the timer in the end.
     *
     * @var int
     */
    private $activeCalls;

    /**
     * Calls per current period, to track current period activity.
     *
     * @var int
     */
    private $calls;

    /** @var \SplQueue */
    private $queue;

    /** @var TimerInterface|null */
    private $timer;

    /**
     * @param LoopInterface $loop
     * @param int $allowedAmount How many promises are allowed per on period of time?
     * @param int $perPeriod One period in seconds
     */
    public function __construct(LoopInterface $loop, $allowedAmount = 5, $perPeriod = 1)
    {
        $this->loop = $loop;
        $this->allowedAmount = $allowedAmount;
        $this->perPeriod = $perPeriod;
        $this->activeCalls = 0;
        $this->queue = new \SplQueue();
    }

    /**
     * @param callable $action
     *
     * @return PromiseInterface
     */
    public function __invoke(callable $action)
    {
        $this->setupTimer();

        // TODO Wait function.
        $promise = new Promise();

        $this->queue->enqueue([$action, $promise]);

        $this->runQueue();

        return $promise;
    }

    private function setupTimer()
    {
        if (!$this->timer) {
            $this->timer = $this->loop->addPeriodicTimer($this->perPeriod, function () {
                $this->calls = 0;

                $this->runQueue();
            });
        }
    }

    public function hasFreeSlots()
    {
        return ($this->calls < $this->allowedAmount);
    }

    public function getQueueSize()
    {
        return count($this->queue);
    }

    private function runQueue()
    {
        while (count($this->queue) && $this->hasFreeSlots()) {
            /** @var PromiseInterface $actionPromise */
            list($action, $actionPromise) = $this->queue->dequeue();

            // TODO Handle all errors...

            $this->activeCalls++;
            $this->calls++;

            try {
                /** @var PromiseInterface $promise */
                $promise = $action();

                // Forwarding.
                $promise->then(
                    function ($result) use ($actionPromise) {
                        $this->activeCalls--;

                        $actionPromise->resolve($result);

                        $this->finishTimer();
                    },
                    function ($reason) use ($actionPromise) {
                        $this->activeCalls--;

                        $actionPromise->reject($reason);

                        $this->finishTimer();
                    }
                );
            } catch (\Exception $error) {
                $this->activeCalls--;
                $this->calls--;

                throw $error;
            } catch (\Throwable $error) {
                $this->activeCalls--;
                $this->calls--;

                throw $error;
            }
        }
    }

    private function finishTimer()
    {
        // Nothing to do. Remove the timer, if there is one...
        if (!$this->activeCalls && !count($this->queue) && $this->timer) {
            $this->loop->cancelTimer($this->timer);
            // Don't use unset(), it completely removes property from the object!
            $this->timer = null;
        }
    }

    public function guzzleMiddleware()
    {
        return function ($handler) {
            return function (RequestInterface $request, $options) use ($handler) {
                // Add the call to the pool's queue and return a proxy promise.
                $poolPromise = $this->__invoke(function () use ($handler, $request, $options) {
                    return $handler($request, $options);
                });

                return $poolPromise;
            };
        };
    }
}
