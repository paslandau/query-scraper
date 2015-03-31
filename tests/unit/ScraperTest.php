<?php

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Subscriber\Mock;
use paslandau\ArrayUtility\ArrayUtil;
use paslandau\GuzzleRotatingProxySubscriber\Exceptions\NoProxiesLeftException;
use paslandau\GuzzleRotatingProxySubscriber\ProxyRotator;
use paslandau\QueryScraper\Exceptions\QueryScraperException;
use paslandau\QueryScraper\Requests\QueryRequestInterface;
use paslandau\QueryScraper\Results\QueryResult;
use paslandau\QueryScraper\Scraper;

class ScraperTest extends PHPUnit_Framework_TestCase
{

    public function test_scrapeQuerys()
    {
        $client = new Client();

        $request = $this->getMock(QueryRequestInterface::class);
        $createRequestFn = function (ClientInterface $client) {
            return $client->createRequest("GET", "/");
        };
        $request->expects($this->any())->method("createRequest")->will($this->returnCallback($createRequestFn));
        $suggests = ["foo"];
        $getSuggestsFn = function (ResponseInterface $response) use ($suggests) {
            if ($response->getStatusCode() == 200) {
                return $suggests;
            }
            throw new QueryScraperException("StatusCode must be 200");
        };
        $request->expects($this->any())->method("getResult")->will($this->returnCallback($getSuggestsFn));

        /** @var QueryRequestInterface $request */
        $tests = [
            "request-successful" => [
                "responses" => [new Response(200)],
                "kss" => new Scraper($client, 1, 5),
                "expected" => [new QueryResult($request, $suggests, null)],
                "expectedException" => null
            ],
            "request-failed" => [
                "responses" => [new Response(404)],
                "kss" => new Scraper($client, 1, 0),
                "expected" => [new QueryResult($request, null, new ClientException("", $request->createRequest($client), null))],
                "expectedException" => null
            ],
            "retry-successful" => [
                "responses" => [new Response(404), new Response(404), new Response(200)],
                "kss" => new Scraper($client, 1, 2),
                "expected" => [new QueryResult($request, $suggests, null)],
                "expectedException" => null
            ],
            "retry-fail" => [
                "responses" => [new Response(404), new Response(404), new Response(200)],
                "kss" => new Scraper($client, 1, 1),
                "expected" => [new QueryResult($request, null, new ClientException("", $request->createRequest($client), null))],
                "expectedException" => null
            ],
            "no-proxies-left" => [
                "responses" => [new NoProxiesLeftException(new ProxyRotator(), $request->createRequest($client), "")],
                "kss" => new Scraper($client, 1, 0),
                "expected" => [new QueryResult($request, null, new NoProxiesLeftException(new ProxyRotator(), $request->createRequest($client), ""))],
                "expectedException" => NoProxiesLeftException::class
            ],
        ];

        foreach ($tests as $test => $data) {
            $mock = new Mock($data["responses"]);
            $client->getEmitter()->attach($mock);
            $requests = [
                $request
            ];

            $this->assertQueryResults($data["kss"], $test, $requests, $data["expected"], $data["expectedException"]);
            $client->getEmitter()->detach($mock);
        }
    }

    public function assertQueryResults(Scraper $kss, $test, array $requests, array $expectedResults, $expectedScraperError = null)
    {
        //check results
        $expected = $this->getQueryResultsAsArray($expectedResults);
        $result = $kss->scrapeQuerys($requests);
        $actual = $this->getQueryResultsAsArray($result);

        $msg = [
            "Error in test $test (checking QueryResults):",
            "Excpected: " . json_encode($expected),
            "Actual   : " . json_encode($actual),
        ];
        $msg = implode("\n", $msg);
        $this->assertEquals($expected, $actual, $msg);

        if (is_array($expected)) {
            $this->assertTrue(ArrayUtil::equals($actual, $expected, true, false, true), $msg);
        } else {
            $this->assertEquals($expected, $actual, $msg);
        }

        //check Exception
        $expected = $expectedScraperError;
        $actual = $kss->getError();
        if ($actual !== null) {
            $actual = get_class($actual);
        }
        $msg = [
            "Error in test $test (checking Scraper error):",
            "Excpected: " . json_encode($expected),
            "Actual   : " . json_encode($actual),
        ];
        $msg = implode("\n", $msg);
        $this->assertEquals($expected, $actual, $msg);
    }

    /**
     * @param QueryResult[] $results
     * @return array
     */
    private function getQueryResultsAsArray(array $results)
    {
        $arr = [];
        foreach ($results as $result) {
            $arr[] = [
                "request" => $result->getRequest(),
                "suggests" => $result->getResult(),
                "exception" => ($result->getException() === null ? null : get_class($result->getException()))
            ];
        }
        return $arr;
    }
}
 