<?php
/**
 * Created by PhpStorm.
 * User: Hirnhamster
 * Date: 02.04.2015
 * Time: 17:39
 */

namespace paslandau\QueryScraper\Results;


trait ProxyFeedbackTrait {

    /**
     * @var string
     */
    protected $proxyResult;

    /**
     * @param string $proxyResult
     */
    public function setProxyResult($proxyResult)
    {
        $this->proxyResult = $proxyResult;
    }

    /**
     * @return string
     */
    public function getProxyResult(){
        return $this->proxyResult;
    }
}