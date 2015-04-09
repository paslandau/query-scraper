<?php

namespace paslandau\QueryScraper\Logging;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

trait LoggerTrait
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function getLogger()
    {
        if (null === $this->logger) {
//            $log = new NullLogger();
            $name = (new \ReflectionClass($this))->getShortName();
            $log = new Logger($name);
            $console = new StreamHandler("php://stdout", Logger::DEBUG);
            $err = new StreamHandler("php://stderr", Logger::ERROR);
            $log->pushHandler($console);
            $log->pushHandler($err);
            $this->logger = $log;
        }

        return $this->logger;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}