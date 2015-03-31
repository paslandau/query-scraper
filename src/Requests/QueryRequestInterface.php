<?php namespace paslandau\QueryScraper\Requests;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\ResponseInterface;

interface QueryRequestInterface
{
    /**
     * @param ClientInterface $client
     * @return RequestInterface
     */
    public function createRequest(ClientInterface $client);

    /**
     * @param ResponseInterface $resp
     * @return mixed
     */
    public function getResult(ResponseInterface $resp);
}