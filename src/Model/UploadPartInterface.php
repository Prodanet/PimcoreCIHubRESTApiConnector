<?php

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Model;

interface UploadPartInterface
{
    public function toArray(): array;

    public function getOrdinal(): int;

    public function setOrdinal(int $ordinal): self;

    public function getId(): string;

    public function setId(string $id): self;
}
