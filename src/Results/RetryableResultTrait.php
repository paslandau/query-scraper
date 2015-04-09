<?php
/**
 * Created by PhpStorm.
 * User: Hirnhamster
 * Date: 02.04.2015
 * Time: 17:37
 */

namespace paslandau\QueryScraper\Results;


trait RetryableResultTrait {

    /**
     * @var bool
     */
    protected $retry;

    /**
     * @param bool $retry
     */
    public function setRetry($retry)
    {
        $this->retry = $retry;
    }

    /**
     * Returns true if a retry should happen
     * @return bool
     */
    public function shouldRetry(){
        return $this->retry;
    }

}