<?php

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Flyststem;

use CIHub\Bundle\SimpleRESTAdapterBundle\Flyststem\Exception\InvalidStreamException;
use Exception;
use php_user_filter;

final class AppendStream
{
    /** @var resource[] */
    private array $streams = [];

    private int $chunkSize;

    public function __construct(iterable $streams = [], int $chunkSize = 8192)
    {
        foreach ($streams as $stream) {
            $this->append($stream);
        }
        $this->chunkSize = $chunkSize;
    }

    public function append(mixed $stream): void
    {
        if (!is_resource($stream)) {
            throw new InvalidStreamException($stream);
        }

        if (get_resource_type($stream) !== 'stream') {
            throw new InvalidStreamException($stream);
        }

        $this->streams[] = $stream;
    }

    /**
     * @throws Exception
     */
    public function getResource()
    {
        if (!$this->streams) {
            return fopen('data://text/plain,', 'r');
        }

        if (count($this->streams) == 1) {
            return reset($this->streams);
        }

        $head = tmpfile();
        fwrite($head, fread($this->streams[0], 8192));
        rewind($head);

        $anonymous = new class($this->streams, $this->chunkSize) extends php_user_filter {
            private static array $streams = [];
            private static int $maxLength;

            public function __construct(array $streams = [], int $maxLength = 8192)
            {
                self::$streams = $streams;
                self::$maxLength = $maxLength;
            }

            /**
             *
             * @param resource $in Incoming bucket brigade
             * @param resource $out Outgoing bucket brigade
             * @param int $consumed Number of bytes consumed
             * @param bool $closing Last bucket brigade in stream?
             */
            public function filter($in, $out, &$consumed, bool $closing): int
            {
                while ($bucket = stream_bucket_make_writeable($in)) {
                    stream_bucket_append($out, $bucket);
                }

                foreach (self::$streams as $stream) {
                    while (feof($stream) !== true) {
                        $bucket = stream_bucket_new($stream, fread($stream, self::$maxLength));
                        stream_bucket_append($out, $bucket);
                    }
                }

                return PSFS_PASS_ON;
            }
        };

        stream_filter_register($filter = bin2hex(random_bytes(32)), get_class($anonymous));
        stream_filter_append($head, $filter);

        return $head;
    }
}