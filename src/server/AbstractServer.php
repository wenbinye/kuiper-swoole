<?php

declare(strict_types=1);

namespace kuiper\swoole\server;

use kuiper\helper\Properties;
use kuiper\swoole\event\AbstractServerEvent;
use kuiper\swoole\event\ServerEventFactory;
use kuiper\swoole\ServerConfig;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

abstract class AbstractServer implements ServerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected const TAG = '['.__CLASS__.'] ';

    /**
     * @var ServerConfig
     */
    private $serverConfig;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ServerEventFactory
     */
    private $serverEventFactory;

    public function __construct(ServerConfig $serverConfig, EventDispatcherInterface $eventDispatcher, ?LoggerInterface $logger)
    {
        $this->serverConfig = $serverConfig;
        $this->eventDispatcher = $eventDispatcher;
        $this->setLogger($logger ?? new NullLogger());
        $this->serverEventFactory = new ServerEventFactory();
    }

    public function dispatch(string $eventName, array $args): ?AbstractServerEvent
    {
        array_unshift($args, $this);
        $event = $this->serverEventFactory->create($eventName, $args);
        if ($event) {
            $this->logger->debug(static::TAG."dispatch event $eventName using ".get_class($event));

            /* @noinspection PhpIncompatibleReturnTypeInspection */
            return $this->getEventDispatcher()->dispatch($event);
        }

        $this->logger->debug(static::TAG."unhandled event $eventName");

        return null;
    }

    public function getServerConfig(): ServerConfig
    {
        return $this->serverConfig;
    }

    public function getServerName(): string
    {
        return $this->getServerConfig()->getServerName();
    }

    public function getSettings(): Properties
    {
        return $this->serverConfig->getSettings();
    }

    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }
}
