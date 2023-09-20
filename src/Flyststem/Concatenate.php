<?php

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Flyststem;

use CIHub\Bundle\SimpleRESTAdapterBundle\Flyststem\Exception\FileNotFoundException;
use Exception;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;

final class Concatenate
{
    public function __construct(private FilesystemOperator $filesystem)
    {
    }

    /**
     * @throws FilesystemException
     * @throws FileNotFoundException
     * @throws Exception
     */
    public function handle(string $target, string $file): bool
    {
        if (!$this->filesystem->fileExists($file)) {
            throw new FileNotFoundException($file);
        }

        $tmpFile = tmpfile();
        $targetFile = $this->filesystem->readStream($target);
        $handle = $this->filesystem->readStream($file);
        foreach ([$targetFile, $handle] as $item) {
            stream_copy_to_stream($item, $tmpFile);
        }

        $this->filesystem->writeStream($target, $tmpFile);
        @unlink(stream_get_meta_data($tmpFile)['uri']);

        return true;
    }
}
