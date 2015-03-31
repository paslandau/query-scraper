<?php

namespace paslandau\QueryScraper\Results;


use paslandau\QueryScraper\Requests\QueryRequestInterface;

class QueryResult implements QueryResultInterface {
    /**
     * @var QueryRequestInterface
     */
    private $request;
    /**
     * @var null|mixed
     */
    private $result;
    /**
     * @var \Exception|null
     */
    private $exception;

    /**
     * @param QueryRequestInterface $request
     * @param mixed|null $result [optional]. Default: null.
     * @param \Exception|null $exception [optional]. Default: null.
     */
    function __construct($request, $result = null, $exception = null)
    {
        $this->exception = $exception;
        $this->request = $request;
        $this->result = $result;
    }

    /**
     * @return \Exception|null
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * @param \Exception|null $exception
     */
    public function setException($exception)
    {
        $this->exception = $exception;
    }

    /**
     * @return QueryRequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param QueryRequestInterface $request
     */
    public function setRequest($request)
    {
        $this->request = $request;
    }

    /**
     * @param null|mixed $result
     */
    public function setResult($result)
    {
        $this->result = $result;
    }


    /**
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }
}