<?php

declare(strict_types=1);

namespace kuiper\swoole\livereload;

class FswatchWatcher implements FileWatcherInterface
{
    private $fswatch = 'fswatch';

    /**
     * @var array
     */
    private $pathList;

    /**
     * @var resource
     */
    private $process;

    /**
     * @var resource
     */
    private $pipe;

    /**
     * FswatchWatcher constructor.
     */
    public function __construct(array $pathList = [])
    {
        $this->pathList = $pathList;
    }

    public function setFswatch(string $fswatch): void
    {
        $this->fswatch = $fswatch;
    }

    public function addPath(string $path): void
    {
        if (!in_array($path, $this->pathList, true)) {
            $this->pathList[] = $path;
            $this->close();
        }
    }

    public function getChangedPaths(): array
    {
        if (!$this->process) {
            $this->open();
        }
        $read = [$this->pipe];
        $select = stream_select($read, $write, $expect, 0);
        if ($select) {
            $content = fread($this->pipe, 8912);

            return array_unique(array_filter(explode("\x0", $content)));
        }

        return [];
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close(): void
    {
        if ($this->process) {
            proc_terminate($this->process);
            unset($this->process, $this->pipe);
        }
    }

    private function open(): void
    {
        $desc = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $path = implode(' ', array_map('escapeshellarg', $this->pathList));
        $this->process = proc_open($this->fswatch." -0 $path", $desc, $pipes);
        if (!$this->process) {
            throw new \InvalidArgumentException('Cannot start fswatch');
        }
        fclose($pipes[0]);
        $this->pipe = $pipes[1];
    }
}
