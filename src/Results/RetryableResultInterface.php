<?php namespace paslandau\QueryScraper\Results;

interface RetryableResultInterface
{
    /**
     * Returns true if a retry should happen
     * @return bool
     */
    public function shouldRetry();
}