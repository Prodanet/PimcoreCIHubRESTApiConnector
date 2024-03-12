<?php

declare(strict_types=1);

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Transformer;

class FilterFieldNameTransformer implements FilterFieldNameTransformerInterface
{
    public function transform(string $field): string
    {
        return 'metaData.'.$field.'.keyword';;
    }
}
