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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Model;

use Pimcore\Logger;
use Pimcore\Model\AbstractModel;
use Pimcore\Model\Exception\NotFoundException;

/**
 * @method save()
 * @method delete()
 */
final class DatahubUploadSession extends AbstractModel
{
    public $data;
    public string $id;

    public UploadParts $parts;

    public int $totalParts = 0;
    public int $fileSize = 0;
    public int $assetId = 0;
    public int $parentId = 0;
    public string $fileName;

    /**
     * get score by id.
     */
    public static function getById(string $id): ?self
    {
        try {
            $obj = new self();
            $obj->getDao()->getById($id);

            return $obj;
        } catch (NotFoundException) {
            throw new \CIHub\Bundle\SimpleRESTAdapterBundle\Exception\NotFoundException("Upload Session with id $id not found");
        }
    }

    /**
     * get score by id.
     */
    public static function hasById(string $id): ?self
    {
        try {
            $obj = new self();
            $obj->getDao()->hasById($id);

            return $obj;
        } catch (NotFoundException) {
            Logger::warn("Upload Session with id $id not found");
        }

        return null;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getParts(): UploadParts
    {
        return $this->parts;
    }

    public function addPart(UploadPart $part): self
    {
        $this->parts->add($part);

        return $this;
    }

    public function setParts(string|array $parts): self
    {
        if (\is_string($parts)) {
            $parts = json_decode($parts, true, 512, \JSON_THROW_ON_ERROR);
        }

        $this->parts = new UploadParts($parts);

        return $this;
    }

    public function getTotalParts(): int
    {
        return $this->totalParts;
    }

    public function setTotalParts(int $totalParts): self
    {
        $this->totalParts = $totalParts;

        return $this;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function setFileSize(int $fileSize): self
    {
        $this->fileSize = $fileSize;

        return $this;
    }

    public function getPartsCount(): int
    {
        return $this->parts->count();
    }

    public function getAssetId(): int
    {
        return $this->assetId;
    }

    public function setAssetId(int $assetId): self
    {
        $this->assetId = $assetId;

        return $this;
    }

    public function getParentId(): int
    {
        return $this->parentId;
    }

    public function setParentId(int $parentId): self
    {
        $this->parentId = $parentId;

        return $this;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): self
    {
        $this->fileName = $fileName;

        return $this;
    }

    /**
     * Rewind.
     */
    public function rewind(): void
    {
        $this->getData();
        reset($this->data);
    }

    /**
     * current.
     */
    public function current(): mixed
    {
        $this->getData();

        return current($this->data);
    }

    /**
     * key.
     */
    public function key(): mixed
    {
        $this->getData();

        return key($this->data);
    }

    /**
     * next.
     */
    public function next(): void
    {
        $this->getData();
        next($this->data);
    }

    /**
     * valid.
     */
    public function valid(): bool
    {
        $this->getData();

        return false !== $this->current();
    }

    public function getTemporaryPath(): string
    {
        return sprintf('tmp-%s-%s', $this->getParentId(), $this->getFileName());
    }

    public function getTemporaryPartExpression(): string
    {
        return sprintf('session-%s-%s', $this->id, $this->fileName);
    }

    public function getTemporaryPartFilename(string $id): string
    {
        return sprintf('%s-%s', $this->getTemporaryPartExpression(), $id);
    }
}
