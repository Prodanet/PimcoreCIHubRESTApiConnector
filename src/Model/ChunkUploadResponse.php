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

use Symfony\Component\Uid\Ulid;

final class ChunkUploadResponse implements ApiResponseInterface
{
    protected string $id;
    protected int $numPartsProcessed = 0;
    protected int $partSize = 0;
    protected array $endpoints = [];
    protected int $totalParts = 0;
    protected string $sessionExpiresAt;

    public function __construct(string $id)
    {
        $this->setId($id);
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        $this->sessionExpiresAt = Ulid::fromString($this->id)
            ->getDateTime()
            ->add(new \DateInterval('PT1H'))
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            ->format('Y-m-d\TH:i:s.u\Z');

        return $this;
    }

    public function addEndpoint($endpoint): self
    {
        $this->endpoints[] = $endpoint;

        return $this;
    }

    public function setNumPartsProcessed(int $numPartsProcessed): self
    {
        $this->numPartsProcessed = $numPartsProcessed;

        return $this;
    }

    public function setPartSize(int $partSize): self
    {
        $this->partSize = $partSize;

        return $this;
    }

    public function setTotalParts(int $totalParts): self
    {
        $this->totalParts = $totalParts;

        return $this;
    }

    public function toArray(): array
    {
        $response = [
            'id' => $this->id,
            'num_parts_processed' => $this->numPartsProcessed,
            'part_size' => $this->partSize,
            'session_expires_at' => $this->sessionExpiresAt,
            'total_parts' => $this->totalParts,
        ];
        if ($this->endpoints !== []) {
            $response['endpoints'] = $this->endpoints;
        }

        return $response;
    }
}
