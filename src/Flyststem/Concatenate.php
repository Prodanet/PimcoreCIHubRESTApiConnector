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

use CIHub\Bundle\SimpleRESTAdapterBundle\Flyststem\Exception\FileNotFoundException;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;

final class Concatenate
{
    public function __construct(private FilesystemOperator $filesystemOperator)
    {
    }

    /**
     * @throws FilesystemException
     * @throws FileNotFoundException
     * @throws \Exception
     */
    public function handle(string $target, string $file): bool
    {
        if (!$this->filesystemOperator->fileExists($file)) {
            throw new FileNotFoundException($file);
        }

        $tmpFile = tmpfile();
        $targetFile = $this->filesystemOperator->readStream($target);
        $handle = $this->filesystemOperator->readStream($file);
        foreach ([$targetFile, $handle] as $item) {
            stream_copy_to_stream($item, $tmpFile);
        }

        $this->filesystemOperator->writeStream($target, $tmpFile);
        @unlink(stream_get_meta_data($tmpFile)['uri']);

        return true;
    }
}
