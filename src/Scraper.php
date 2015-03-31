<?php

namespace paslandau\QueryScraper;


use GuzzleHttp\ClientInterface;
use GuzzleHttp\Event\AbstractTransferEvent;
use GuzzleHttp\Event\EndEvent;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Pool;
use paslandau\GuzzleRotatingProxySubscriber\Exceptions\NoProxiesLeftException;
use paslandau\QueryScraper\Exceptions\QueryScraperException;
use paslandau\QueryScraper\Requests\QueryRequestInterface;
use paslandau\QueryScraper\Results\QueryResult;

class Scraper
{
    const GUZZLE_REQUEST_ID_KEY = "query_suggest_request_id";
    const GUZZLE_REQUEST_RETRIES = "query_suggest_request_retries";

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var int
     */
    private $parallel;

    /**
     * @var int
     */
    private $maxRetries;

    /**
     * @var \Exception
     */
    private $error;

    /**
     * @param ClientInterface $client
     * @param int $parallel . [optional]. Default: 5.
     * @param int $maxRetries . [optional]. Default: 3.
     */
    function __construct(ClientInterface $client, $parallel = null, $maxRetries = null)
    {
        $this->client = $client;
        if ($parallel === null) {
            $parallel = 5;
        }
        $this->parallel = $parallel;
        if ($maxRetries === null) {
            $maxRetries = 3;
        }
        $this->maxRetries = $maxRetries;
        $this->error = null;
    }

    /**
     * @param QueryRequestInterface[] $queryRequests
     * @return QueryResult[]
     */
    public function scrapeQuerys(array $queryRequests)
    {
        $this->error = null;
        $result = [];

        // prepare requests
        $requests = [];
        foreach ($queryRequests as $key => $queryRequest) {
            $req = $queryRequest->createRequest($this->client);
            $req->getConfig()->set(self::GUZZLE_REQUEST_ID_KEY, $key);
            $req->getConfig()->set(self::GUZZLE_REQUEST_RETRIES, 0);
            $requests[$key] = $req;
            $result[$key] = new QueryResult($queryRequest, [], new QueryScraperException("Request has not been executed!"));
        }

        $complete = $this->getOnComplete();

        $error = $this->getOnError();

        $end = $this->getOnEnd($complete, $error, $result, $queryRequests);

        $pool = new Pool($this->client, $requests,
            [
                "pool_size" => $this->parallel,
                "complete" => $end,
                "error" => $end,
                "end" => function (EndEvent $event) use (&$pool) {
                    $exception = $event->getException();
//                    echo "In terminateFn filter, ".$event->getRequest()->getConfig()->get(QueryScraperScraper::GUZZLE_REQUEST_ID_KEY)."\n";
                    if ($exception instanceof NoProxiesLeftException) {
//                        echo $exception->getMessage();
                        $this->error = $exception;
                        /** @var Pool $pool */
                        $pool->cancel();
                    }
                }
            ]);
        $pool->wait();

        return $result;
    }

    /**
     * @return callable
     */
    public function getOnComplete()
    {
        $complete = function (QueryRequestInterface $request, ResponseInterface $response) {
            $suggests = [];
            $exception = null;
            try {
                $suggests = $request->getResult($response);
            } catch (\Exception $e) {
                $exception = $e;
            }
            $queryResult = new QueryResult($request, $suggests, $exception);
            return $queryResult;
        };
        return $complete;
    }

    /**
     * @return callable
     */
    public function getOnError()
    {
        $error = function (QueryRequestInterface $request, \Exception $exception) {
            $queryResult = new QueryResult($request, null, $exception);
            return $queryResult;
        };
        return $error;
    }

    /**
     * @param callable $complete
     * @param callable $error
     * @param array $result
     * @param array $queryRequests
     * @return callable
     */
    public function getOnEnd(callable $complete, callable $error, array &$result, array &$queryRequests)
    {
        $end = function (AbstractTransferEvent $event) use ($complete, $error, &$result, &$queryRequests) {
//            echo "In querySuggestEnd filter, " . $event->getRequest()->getConfig()->get(QueryScraperScraper::GUZZLE_REQUEST_ID_KEY) . "\n";
            $request = $event->getRequest();
            $response = $event->getResponse();
            $exception = null;
            if ($event instanceof ErrorEvent) {
                $exception = $event->getException();
            }
            $requestId = $request->getConfig()->get(self::GUZZLE_REQUEST_ID_KEY);
            $queryRequest = $queryRequests[$requestId];
            if ($exception === null) {
                $queryResult = $complete($queryRequest, $response);
            } else {
                $queryResult = $error($queryRequest, $exception);
            }
            /** @var QueryResult $queryResult */
            if ($queryResult->getException() !== null) {
//                echo $queryResult->getException()->getMessage() . "\n";
                $curRetries = $request->getConfig()->get(self::GUZZLE_REQUEST_RETRIES);
                if ($curRetries < $this->maxRetries) {
                    $curRetries++;
//                    echo "Retring $requestId ($curRetries)\n";
                    $request->getConfig()->set(self::GUZZLE_REQUEST_RETRIES, $curRetries);
                    $event->retry();
                    return;
                }
//                echo "Exceeded retries for $requestId ($curRetries)\n";
            }
            $result[$requestId] = $queryResult;
        };
        return $end;
    }

    /**
     * @return \Exception
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @return ClientInterface
     */
    public function getClient()
    {
        return $this->client;
    }
}