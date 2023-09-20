<?php

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Model;

interface ApiResponseInterface
{
    public function toArray(): array;
}
