<?php

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
    public string $id;

    public UploadParts $parts;

    public int $totalParts = 0;
    public int $fileSize = 0;
    public int $assetId = 0;
    public int $parentId = 0;
    public string $fileName;

    /**
     * get score by id
     */
    public static function getById(string $id): ?self
    {
        try {
            $obj = new self;
            $obj->getDao()->getById($id);
            return $obj;
        } catch (NotFoundException $ex) {
            throw new \CIHub\Bundle\SimpleRESTAdapterBundle\Exception\NotFoundException("Upload Session with id $id not found");
        }

        return null;
    }

    /**
     * get score by id
     */
    public static function hasById(string $id): ?self
    {
        try {
            $obj = new self;
            $obj->getDao()->hasById($id);
            return $obj;
        } catch (NotFoundException $ex) {
            Logger::warn("Upload Session with id $id not found");
        }

        return null;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): DatahubUploadSession
    {
        $this->id = $id;
        return $this;
    }

    public function getParts(): UploadParts
    {
        return $this->parts;
    }

    public function setParts(string|array $parts): DatahubUploadSession
    {
        if (is_string($parts)) {
            $parts = json_decode($parts, true);
        }

        $this->parts = new UploadParts($parts);
        return $this;
    }

    public function addPart(UploadPart $part): DatahubUploadSession
    {
        $this->parts->add($part);

        return $this;
    }

    public function getTotalParts(): int
    {
        return $this->totalParts;
    }

    public function setTotalParts(int $totalParts): DatahubUploadSession
    {
        $this->totalParts = $totalParts;
        return $this;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function setFileSize(int $fileSize): DatahubUploadSession
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

    public function setAssetId(int $assetId): DatahubUploadSession
    {
        $this->assetId = $assetId;
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

        return $this->current() !== false;
    }

    /**
     * current.
     */
    public function current(): mixed
    {
        $this->getData();

        return current($this->data);
    }

    public function getTemporaryPath(): string
    {
        return sprintf('tmp-%s-%s', $this->getParentId(), $this->getFileName());
    }

    public function getParentId(): int
    {
        return $this->parentId;
    }

    public function setParentId(int $parentId): DatahubUploadSession
    {
        $this->parentId = $parentId;
        return $this;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): DatahubUploadSession
    {
        $this->fileName = $fileName;
        return $this;
    }

    public function getTemporaryPartFilename(string $id): string
    {
        return sprintf('%s-%s', $this->getTemporaryPartExpression(), $id);
    }

    public function getTemporaryPartExpression(): string
    {
        return sprintf('session-%s-%s', $this->id, $this->fileName);
    }
}
