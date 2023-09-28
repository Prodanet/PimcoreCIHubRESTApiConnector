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

final class UploadPart implements UploadPartInterface
{
    protected string $id;
    protected int $size;
    protected string $hash;
    protected int $ordinal = 0;

    public function __construct(array $part = [])
    {
        if ([] !== $part) {
            $this->setId($part['id']);
            $this->setSize($part['size'] ?? 0);
            $this->setHash($part['hash'] ?? '');
            $this->setOrdinal($part['ordinal'] ?? 0);
        }
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function setHash(string $hash): self
    {
        $this->hash = $hash;

        return $this;
    }

    public function setOrdinal(int $ordinal): self
    {
        $this->ordinal = $ordinal;

        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getOrdinal(): int
    {
        return $this->ordinal;
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
}
