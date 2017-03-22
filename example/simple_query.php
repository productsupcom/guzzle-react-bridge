<?php

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Productsup\GuzzleReactBridge\CurlMultiHandler;
use function Productsup\GuzzleReactBridge\run;

require __DIR__ . '/../vendor/autoload.php';

run(function ($loop) use ($genFn) {
    // Logger to show pretty messages with timestamps.
    $logger = new Logger('default');
    $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

    $httpHandler = new CurlMultiHandler($loop);
    $stack = HandlerStack::create($httpHandler);

    $httpClient = new Client([
        'handler' => $stack,
    ]);

    $promise = $httpClient->getAsync('https://google.com')->then(
        function ($result) use ($logger) { $logger->info('Query completed!'); },
        function ($reason) use ($logger) { $logger->error('Query failed :('); }
    );

    /*
     * The promise isn't completed yet, but it will be resolved in the future, the event loop will take care of it.
     * We don't need to call Promise::wait()!
     */
});
