<?php

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Model;

use ArrayIterator;
use Traversable;

final class UploadParts implements UploadPartsInterface
{
    protected array $parts = [];

    public function __construct(array $parts = [])
    {
        if (!empty($parts)) {
            foreach ($parts as $part) {
                if (is_array($part)) {
                    $this->add(new UploadPart($part));
                } else if ($part instanceof UploadPartInterface) {
                    $this->add($part);
                }
            }
        }
    }

    public function toArray(): array
    {
        return array_map(function (UploadPartInterface $part) {
            return $part->toArray();
        }, $this->parts);
    }

    public function add(UploadPartInterface $part): UploadPartsInterface
    {
        $this->parts[] = $part;
        return $this;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->parts);
    }

    public function count(): int
    {
        return count($this->parts);
    }
}
