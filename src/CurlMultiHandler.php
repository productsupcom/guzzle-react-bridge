<?php

namespace Productsup\GuzzleReactBridge;

use GuzzleHttp\Handler\CurlMultiHandler as BaseCurlMultiHandler;
use Psr\Http\Message\RequestInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;

class CurlMultiHandler extends BaseCurlMultiHandler
{
    /** @var LoopInterface */
    private $loop;

    /** @var TimerInterface */
    private $timer;

    private $activeRequests = 0;

    public function __construct(LoopInterface $loop, array $options = [])
    {
        // Don't block by cURL, use external event loop and timers.
        $options['select_timeout'] = 0;

        parent::__construct($options);

        $this->loop = $loop;
    }

    public function __invoke(RequestInterface $request, array $options)
    {
        $this->activeRequests++;

        // Enable executor.
        $this->updateTimer($options);

        $responsePromise = parent::__invoke($request, $options);

        $responsePromise->then(
            $this->responseCompleteHandler(),
            $this->responseCompleteHandler()
        );

        return $responsePromise;
    }

    private function updateTimer($options = [])
    {
        // Activate timer if required and there is no active one.
        if ($this->activeRequests && !$this->timer) {
            // TODO Delay the first execution, if the delay option is present.
            $this->timer = $this->loop->addPeriodicTimer(0.001, [$this, 'tick']);
        }

        // Deactive timer if there are no more active requests.
        if (!$this->activeRequests && $this->timer) {
            $this->timer->cancel();
            unset($this->timer);
        }
    }

    private function responseCompleteHandler()
    {
        return static function () {
            $this->activeRequests--;
            $this->updateTimer();
        };
    }
}
