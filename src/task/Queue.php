<?php

declare(strict_types=1);

namespace kuiper\swoole\task;

use kuiper\annotations\AnnotationReaderAwareInterface;
use kuiper\annotations\AnnotationReaderAwareTrait;
use kuiper\swoole\annotation\TaskProcessor;
use kuiper\swoole\exception\TaskProcessorNotFoundException;
use kuiper\swoole\server\ServerInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Queue implements QueueInterface, DispatcherInterface, AnnotationReaderAwareInterface, LoggerAwareInterface
{
    use AnnotationReaderAwareTrait;
    use LoggerAwareTrait;

    protected const TAG = '['.__CLASS__.'] ';

    /**
     * @var ServerInterface
     */
    private $server;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var ProcessorInterface[]
     */
    private $processors;

    public function __construct(ServerInterface $server, ?ContainerInterface $container, ?LoggerInterface $logger)
    {
        $this->server = $server;
        $this->container = $container;
        $this->setLogger($logger ?? new NullLogger());
    }

    /**
     * {@inheritdoc}
     */
    public function put($task, int $workerId = -1, callable $onFinish = null): int
    {
        $this->getProcessor($task);

        $taskId = $this->server->task($task, $workerId, $onFinish);

        return is_int($taskId) ? $taskId : 0;
    }

    public function registerProcessor(string $taskClass, $handler): void
    {
        if (is_string($handler)) {
            if (!$this->container) {
                throw new \InvalidArgumentException('container not set');
            }
            $handler = $this->container->get($handler);
        }
        if (!($handler instanceof ProcessorInterface)) {
            throw new \InvalidArgumentException("task handler '".get_class($handler)."' should implement ".ProcessorInterface::class);
        }
        $this->processors[$taskClass] = $handler;
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch($task): void
    {
        try {
            $result = $this->getProcessor($task)->process($task);
            if (isset($result)) {
                $this->server->finish($result);
            }
        } catch (\Exception $e) {
            $this->logger->error(static::TAG.'dispatch error', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function getProcessor(object $task): ProcessorInterface
    {
        $taskClass = get_class($task);
        if (isset($this->processors[$taskClass])) {
            return $this->processors[$taskClass];
        }

        if ($this->annotationReader) {
            /** @var TaskProcessor $annotation */
            $annotation = $this->annotationReader->getClassAnnotation(new \ReflectionClass($taskClass), TaskProcessor::class);
            if ($annotation) {
                $this->registerProcessor($taskClass, $annotation->name);

                return $this->processors[$taskClass];
            }
        }

        $handler = $taskClass.'Processor';
        if (class_exists($handler)) {
            $this->registerProcessor($taskClass, $handler);

            return $this->processors[$taskClass];
        }

        throw new TaskProcessorNotFoundException('Cannot find task processor for task '.$taskClass);
    }
}
