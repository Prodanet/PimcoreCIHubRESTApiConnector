<?php

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Model;

final class UploadPart implements UploadPartInterface
{
    protected string $id;
    protected int $size;
    protected string $hash;
    protected int $ordinal = 0;

    public function __construct(array $part = [])
    {
        if (!empty($part)) {
            $this->setId($part['id']);
            $this->setSize($part['size'] ?? 0);
            $this->setHash($part['hash'] ?? '');
            $this->setOrdinal($part['ordinal'] ?? 0);
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'size' => $this->getSize(),
            'hash' => $this->getHash(),
            'ordinal' => $this->getOrdinal(),
        ];
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

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;
        return $this;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function setHash(string $hash): self
    {
        $this->hash = $hash;
        return $this;
    }

    public function getOrdinal(): int
    {
        return $this->ordinal;
    }

    public function setOrdinal(int $ordinal): UploadPart
    {
        $this->ordinal = $ordinal;
        return $this;
    }
}
