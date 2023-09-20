<?php

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Flyststem;

use CIHub\Bundle\SimpleRESTAdapterBundle\Flyststem\Exception\FileNotFoundException;
use Exception;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;

final class Append
{
    public function __construct(private Filesystem $filesystem)
    {
    }

    /**
     * @throws FilesystemException
     * @throws Exception
     */
    public function handle(string $target, mixed $content): bool
    {
        if (!$this->filesystem->has($target)) {
            throw new FileNotFoundException($target);
        }

        $this->filesystem->move($target, $backup = $target . '.backup');

        $contentToAppend = is_resource($content) ? $content : fopen('data://text/plain,' . $content, 'r');
        $stream = (new AppendStream([
            $this->filesystem->readStream($backup),
            $contentToAppend,
        ]))->getResource();

        $this->filesystem->writeStream($target, $stream);

        $this->filesystem->delete($backup);

        return true;
    }
}