<?php

declare(strict_types=1);

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Transformer;

interface FilterFieldNameTransformerInterface
{
    public function transform(string $field): string;
}
