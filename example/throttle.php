<?php

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Productsup\GuzzleReactBridge\CurlMultiHandler;
use Productsup\GuzzleReactBridge\Throttler;
use Psr\Log\LoggerInterface;
use function GuzzleHttp\Promise\each_limit;
use function Productsup\GuzzleReactBridge\run;

require __DIR__ . '/../vendor/autoload.php';

$genFn = function (ClientInterface $client, LoggerInterface $logger) {
    $links = array_fill(0, 10, 'https://ya.ru');

    foreach ($links as $link) {
        $logger->debug('Query placed in the queue...');

        yield $client->getAsync($link)->then(
            function ($result) use ($logger) { $logger->info('Query completed.'); },
            function ($reason) use ($logger) { $logger->error('Query failed'); }
        );
    }
};

run(function ($loop) use ($genFn) {
    // Logger to show pretty messages with timestamps.
    $logger = new Logger('default');
    $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

    $httpHandler = new CurlMultiHandler($loop);
    $stack = HandlerStack::create($httpHandler);

    /*
     * RedirectMiddleware should be placed after the pool, if you want NOT to count redirect as new queries...
     * By default each redirect is a new query and will be balanced accordingly.
     */
    $stack->push((new Throttler($loop, 2, 5))->guzzleMiddleware());

    $httpClient = new Client([
        'handler' => $stack,
    ]);

    $gen = $genFn($httpClient, $logger);

    // 2 active + always 2 spare in the queue.
    each_limit($gen, 4);
});
