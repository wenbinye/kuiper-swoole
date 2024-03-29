<?php

declare(strict_types=1);

namespace kuiper\swoole\task;

use kuiper\swoole\event\BootstrapEvent;
use kuiper\swoole\event\StartEvent;
use kuiper\swoole\event\TaskEvent;
use kuiper\swoole\event\WorkerStartEvent;
use kuiper\swoole\fixtures\FooTask;
use kuiper\swoole\server\ServerInterface;
use kuiper\swoole\SwooleServerTestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Swoole\Timer;
use Symfony\Component\EventDispatcher\EventDispatcher;

class QueueTest extends SwooleServerTestCase
{
    private const TAG = '['.__CLASS__.'] ';

    public function testName()
    {
        $container = $this->createContainer();
        $logger = $container->get(LoggerInterface::class);

        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $container->get(EventDispatcherInterface::class);
        $eventDispatcher->addListener(BootstrapEvent::class, function () use ($container, $logger, $eventDispatcher) {
            /** @var Queue $queue */
            $queue = $container->get(QueueInterface::class);
            $eventDispatcher->addListener(WorkerStartEvent::class, function (WorkerStartEvent $event) use ($logger, $queue) {
                if (0 === $event->getWorkerId()) {
                    $logger->info(self::TAG.'put task');
                    $queue->put(new FooTask('foo'));
                }
            });
            $eventDispatcher->addListener(TaskEvent::class, function (TaskEvent $event) use ($logger, $queue) {
                $logger->info(self::TAG.'consume task', ['task' => $event->getData()]);
                $this->assertInstanceOf(FooTask::class, $event->getData());
                $this->assertEquals('foo', $event->getData()->getArg());
                $queue->dispatch($event->getData());
            });
        });
        $eventDispatcher->addListener(StartEvent::class, function (StartEvent $event) use ($container, $logger, $eventDispatcher) {
            $logger->info(self::TAG.'server started');
            Timer::after(1000, function () use ($event) {
                $event->getServer()->stop();
            });
        });
        $logger->info(self::TAG.'start server');
        $container->get(ServerInterface::class)->start();
        $this->assertTrue(true, 'ok');
    }
}
