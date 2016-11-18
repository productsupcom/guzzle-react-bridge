<?php

namespace Productsup\GuzzleReactBridge;

use GuzzleHttp\Promise\TaskQueue;
use React\EventLoop\LoopInterface;

class ReactTaskQueue extends TaskQueue
{
    /** @var  LoopInterface */
    private $loop;

    /**
     * @param LoopInterface $loop
     */
    public function __construct(LoopInterface $loop)
    {
        parent::__construct(true);

        $this->loop = $loop;
    }

    public function add(callable $task)
    {
        parent::add($task);

        $this->loop->nextTick([$this, 'run']);
    }
}
