<?php

namespace paslandau\QueryScraper\Exceptions;


class QueryScraperException extends \Exception{

    public function __construct($message, $code = null, $previous = null){

        if($code === null){
            $code = 0;
        }
        parent::__construct($message, $code, $previous);
    }
}