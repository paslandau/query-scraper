<?php

namespace paslandau\QueryScraper;


use GuzzleHttp\ClientInterface;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Event\AbstractTransferEvent;
use GuzzleHttp\Event\EndEvent;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Pool;
use GuzzleHttp\Subscriber\Cookie;
use paslandau\GuzzleRotatingProxySubscriber\Exceptions\NoProxiesLeftException;
use paslandau\GuzzleRotatingProxySubscriber\Proxy\RotatingProxyInterface;
use paslandau\QueryScraper\Exceptions\QueryScraperException;
use paslandau\QueryScraper\Logging\LoggerTrait;
use paslandau\QueryScraper\Requests\QueryRequestInterface;
use paslandau\QueryScraper\Results\ProxyFeedbackInterface;
use paslandau\QueryScraper\Results\Result;
use paslandau\QueryScraper\Results\RetryableResultInterface;

class Scraper
{
    use LoggerTrait;

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
     * @var \ArrayIterator
     */
    private $requestGenerator;

    private $blocked = 0;

    private $done = 0;

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
        $this->requestGenerator = new \ArrayIterator();
    }

    /**
     * @param QueryRequestInterface[] $queryRequests
     * @return Result[]
     */
    public function scrapeQuerys(array $queryRequests)
    {
        $this->error = null;
        $result = [];

        // prepare requests
        $requests = [];
        foreach ($queryRequests as $key => $queryRequest) {
            $req = $this->createRequest($queryRequest, $key);
            $requests[$key] = $req;
            $this->requestGenerator->append($req);
            $result[$key] = new Result($queryRequest, null, new QueryScraperException("Request has not been executed!"));
        }

        $end = $this->getOnEnd($result, $queryRequests);
        $this->getLogger()->debug("Start scraping with {$this->parallel} parallel requests");

        $pool = new Pool($this->client, $this->requestGenerator,
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
     * @param array $result
     * @param array $queryRequests
     * @return callable
     */
    public function getOnEnd(array &$result, array &$queryRequests)
    {
        $end = function (AbstractTransferEvent $event) use (&$result, &$queryRequests) {
//            echo "In querySuggestEnd filter, " . $event->getRequest()->getConfig()->get(QueryScraperScraper::GUZZLE_REQUEST_ID_KEY) . "\n";
            $request = $event->getRequest();
            $response = $event->getResponse();
            $exception = null;
            if ($event instanceof ErrorEvent) {
                $exception = $event->getException();
            }
            $requestId = $request->getConfig()->get(self::GUZZLE_REQUEST_ID_KEY);
            /** @var QueryRequestInterface $queryRequest */
            $queryRequest = $queryRequests[$requestId];
            $queryResult = $queryRequest->getResult($request, $response, $exception);

            //proxy feedback
            if ($queryResult instanceof ProxyFeedbackInterface) {
                $proxyResult = $queryResult->getProxyResult();
                $this->getLogger()->debug("[Request: $requestId] Proxy-Feedback for {$request->getConfig()->get("proxy")} {$request->getUrl()}: {$proxyResult}");
                if($proxyResult == "blocked") {
                    $this->blocked++;
                }
                $this->getLogger()->debug("Blocked: {$this->blocked}");
//                $emitter = $request->getEmitter();
//                foreach ($emitter->listeners("complete") as $listener) {
//                    if (is_array($listener) && $listener[0] instanceof Cookie) {
//                        /**
//                         * @var CookieJar $jar
//                         */
//                        $jar = $listener[0]->getCookieJar();
//                        foreach($jar as $cookie){
//                            $this->getLogger()->debug("Cookie: {$cookie->getName()} = {$cookie->getValue()}");
//                        }
//                    }
//                }
                $request->getConfig()->set(RotatingProxyInterface::GUZZLE_CONFIG_KEY_REQUEST_RESULT, $proxyResult);
            }

            //retry
            if ($queryResult instanceof RetryableResultInterface && $queryResult->shouldRetry()) {

                $curRetries = $request->getConfig()->get(self::GUZZLE_REQUEST_RETRIES);
                // NOTE: We cannot use $event->retry(); because it will retry the exact URL that was in the last request.
                // This becomes a problem when we're dealing with redirects, e.g. while Google SERP scraping.
                // Google will redirect to a https://ipv4.google.com/sorry/IndexRedirect... page and the retry will retry that URL - which makes no sense
                // To solve this, we re-generate the original request
                $req = $this->createRequest($queryRequest, $requestId);
                if ($curRetries < $this->maxRetries) {
                    $this->getLogger()->debug("[Request: $requestId] Retrying {$req->getUrl()}... ({$curRetries}/{$this->maxRetries})");
                    $curRetries++;
                    $req->getConfig()->set(self::GUZZLE_REQUEST_RETRIES, $curRetries);
                    $this->requestGenerator->append($req);
                    return;
                }
                $this->getLogger()->debug("Giving up on {$req->getUrl()} after {$curRetries} retries");
            }
            $this->done++;
            $this->getLogger()->debug("Scraped {$this->done} or ".count($queryRequests));
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

    /**
     * @param QueryRequestInterface $queryRequest
     * @param int $key
     * @return RequestInterface
     */
    private function createRequest(QueryRequestInterface $queryRequest, $key)
    {
        $req = $queryRequest->createRequest($this->client);
        $req->getConfig()->set(self::GUZZLE_REQUEST_ID_KEY, $key);
        $req->getConfig()->set(self::GUZZLE_REQUEST_RETRIES, 0);
        return $req;
    }
}