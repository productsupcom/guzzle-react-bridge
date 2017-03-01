<?php


use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\TransferStats;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use function GuzzleHttp\Promise\each_limit;

require __DIR__ . '/../vendor/autoload.php';

class LastResponses
{
    private $timeline = [];

    private $sorted = [];

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function add($time)
    {
        array_unshift($this->timeline, $time);

        // Maintain only 100 last elements.
        $this->sorted = array_slice($this->timeline, 0, 100);

        // And keep it sorted, obviously.
        sort($this->sorted);
    }

    public function percentile95()
    {
        $index = (int) ceil(count($this->sorted) * 0.95);

        return $this->sorted[$index];
    }

    public function concurrencyLevelFn()
    {
        return function ($currentAmount) {
            $threshold = 2;
            $min = 3;
            $max = 15;

            $result = $max;
            if ($this->percentile95() >= $threshold) {
                $result = $currentAmount >= $min ? $currentAmount : $min;
            }

            $this->logger->info('New concurrency: ' . $result);

            return $result;
        };
    }

    public function onStatFn()
    {
        return function (TransferStats $stats) {
            $this->add($stats->getTransferTime());
        };
    }
}

$genFn = function (ClientInterface $client, callable $statsFn, LoggerInterface $logger) {
    $links = array_fill(0, 100, 'http://www.deelay.me/3000/http://deelay.me/img/1000ms.gif');

    foreach ($links as $link) {
        $logger->debug('Query placed in the queue...');

        yield $client->getAsync($link, ['on_stats' => $statsFn])->then(
            function ($result) use ($logger) {
                $logger->info('Query completed!');
            },
            function ($reason) use ($logger) {
                $logger->error('Query failed :(');
            }
        );
    }
};

// Logger to show pretty messages with timestamps.
$logger = new Logger('default');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

$responseCollection = new LastResponses($logger);

$stack = HandlerStack::create();

$httpClient = new Client([
    'handler' => $stack,
]);

$gen = $genFn($httpClient, $responseCollection->onStatFn(), $logger);

// 2 active + always 2 spare in the queue.
$p = each_limit($gen, $responseCollection->concurrencyLevelFn());

$p->wait();
