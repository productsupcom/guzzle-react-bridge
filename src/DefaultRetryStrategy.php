<?php

namespace Productsup\GuzzleReactBridge;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

class DefaultRetryStrategy
{
    public function delayCalculator()
    {
        return function ($numberOfRetries) {
            return 1000 * $numberOfRetries;
        };
    }

    public function decider()
    {
        return function (
            $retries,
            Request $request,
            Response $response = null,
            RequestException $exception = null
        ) {
            // Don't retry if we have run out of retries.
            if ($retries >= 5) {
                return false;
            }
            $shouldRetry = false;
            // Retry connection exceptions.
            if ($exception instanceof ConnectException) {
                $shouldRetry = true;
            }
            if ($response) {
                // Retry on server errors.
                if ($response->getStatusCode() >= 500) {
                    $shouldRetry = true;
                }
            }

            return $shouldRetry;
        };
    }
}
