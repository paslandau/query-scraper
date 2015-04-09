<?php

namespace paslandau\QueryScraper\Results;


use paslandau\QueryScraper\Requests\QueryRequestInterface;

interface ResultInterface
{

    /**
     * @return \Exception|null
     */
    public function getException();

    /**
     * @return QueryRequestInterface
     */
    public function getRequest();

    /**
     * Override the return value in implementing classes!
     * @return mixed
     */
    public function getResult();
}