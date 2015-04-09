<?php namespace paslandau\QueryScraper\Requests;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\ResponseInterface;
use paslandau\QueryScraper\Results\QueryResultInterface;
use paslandau\QueryScraper\Results\ResultInterface;

interface QueryRequestInterface
{
    /**
     * @param ClientInterface $client
     * @return RequestInterface
     */
    public function createRequest(ClientInterface $client);

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $resp
     * @param \Exception $exception
     * @return \paslandau\QueryScraper\Results\ResultInterface
     */
    public function getResult(RequestInterface $request, ResponseInterface $resp = null, \Exception $exception = null);
}