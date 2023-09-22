<?php

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Model;

use Countable;
use IteratorAggregate;

interface UploadPartsInterface extends IteratorAggregate, Countable
{
    public function toArray(): array;
    public function add(UploadPartInterface $part): self;
    public function count(): int;
}
