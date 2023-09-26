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
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;

final class Append
{
    public function __construct(private Filesystem $filesystem)
    {
    }

    /**
     * @throws FilesystemException
     * @throws \Exception
     */
    public function handle(string $target, mixed $content): bool
    {
        if (!$this->filesystem->has($target)) {
            throw new FileNotFoundException($target);
        }

        $this->filesystem->move($target, $backup = $target.'.backup');

        $contentToAppend = \is_resource($content) ? $content : fopen('data://text/plain,'.$content, 'r');
        $stream = (new AppendStream([
            $this->filesystem->readStream($backup),
            $contentToAppend,
        ]))->getResource();

        $this->filesystem->writeStream($target, $stream);

        $this->filesystem->delete($backup);

        return true;
    }
}
