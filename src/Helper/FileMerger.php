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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Helper;

class FileMerger
{
    /**
     * @var bool|resource
     */
    protected $destinationFile;

    /**
     * @throws \Exception
     */
    public function __construct(string $targetFile)
    {
        // open the target file
        if (!$this->destinationFile = @fopen($targetFile, 'a')) {
            throw new \Exception('Failed to open output stream.', 102);
        }
    }

    /**
     * @throws \Exception
     */
    public function appendFile($sourceFilePath): self
    {
        // open the new uploaded chunk
        if (!$in = $sourceFilePath) {
            @fclose($this->destinationFile);
            throw new \Exception('Failed to open input stream', 101);
        }

        // read and write in buffs
        while ($buff = fread($in, 4096)) {
            fwrite($this->destinationFile, $buff);
        }

        @fclose($in);

        return $this;
    }

    public function close(): void
    {
        @fclose($this->destinationFile);
    }
}
