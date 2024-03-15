<?php

/**
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @license    https://choosealicense.com/licenses/gpl-3.0/ GNU General Public License v3.0
 * @copyright  Copyright (c) 2023 Brand Oriented sp. z o.o. (https://brandoriented.pl)
 * @copyright  Copyright (c) 2021 CI HUB GmbH (https://ci-hub.com)
 */

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Flyststem;

use CIHub\Bundle\SimpleRESTAdapterBundle\Flyststem\Exception\InvalidStreamException;

final class AppendStream
{
    /** @var resource[] */
    private array $streams = [];

    public function __construct(iterable $streams = [], private readonly int $chunkSize = 8192)
    {
        foreach ($streams as $stream) {
            $this->append($stream);
        }
    }

    public function append(mixed $stream): void
    {
        if (!\is_resource($stream)) {
            throw new InvalidStreamException($stream);
        }

        if ('stream' !== get_resource_type($stream)) {
            throw new InvalidStreamException($stream);
        }

        $this->streams[] = $stream;
    }

    /**
     * @throws \Exception
     */
    public function getResource()
    {
        if (!$this->streams) {
            return fopen('data://text/plain,', 'r');
        }

        if (1 == \count($this->streams)) {
            return reset($this->streams);
        }

        $head = tmpfile();
        fwrite($head, fread($this->streams[0], 8192));
        rewind($head);

        $anonymous = new class($this->streams, $this->chunkSize) extends \php_user_filter {
            private static array $streams = [];

            private static int $maxLength;

            public function __construct(array $streams = [], int $maxLength = 8192)
            {
                self::$streams = $streams;
                self::$maxLength = $maxLength;
            }

            /**
             * @param resource $in       Incoming bucket brigade
             * @param resource $out      Outgoing bucket brigade
             * @param int      $consumed Number of bytes consumed
             * @param bool     $closing  Last bucket brigade in stream?
             */
            public function filter($in, $out, &$consumed, bool $closing): int
            {
                while (($bucket = stream_bucket_make_writeable($in)) instanceof \stdClass) {
                    stream_bucket_append($out, $bucket);
                }

                foreach (self::$streams as $stream) {
                    while (!feof($stream)) {
                        $bucket = stream_bucket_new($stream, fread($stream, self::$maxLength));
                        stream_bucket_append($out, $bucket);
                    }
                }

                return \PSFS_PASS_ON;
            }
        };

        stream_filter_register($filter = bin2hex(random_bytes(32)), $anonymous::class);
        stream_filter_append($head, $filter);

        return $head;
    }
}
