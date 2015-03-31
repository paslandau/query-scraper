<?php

namespace paslandau\QueryScraper\Results;


use paslandau\QueryScraper\Requests\QueryRequestInterface;

interface QueryResultInterface {

    /**
     * @return \Exception|null
     */
    public function getException();

    /**
     * @param \Exception|null $exception
     */
    public function setException($exception);

    /**
     * @return QueryRequestInterface
     */
    public function getRequest();

    /**
     * @param QueryRequestInterface $request
     */
    public function setRequest($request);

    /**
     * @return mixed
     */
    public function getResult();

    /**
     * @param null|mixed $result
     */
    public function setResult($result);


} 