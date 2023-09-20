<?php

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Flyststem\Exception;

use RuntimeException;

class InvalidStreamException extends RuntimeException
{
    public function __construct(mixed $variable)
    {
        $message = 'Invalid stream resource given: ' . gettype($variable);

        if (is_resource($variable)) {
            $message .= ' ' . get_resource_type($variable);
        } elseif (is_object($variable)) {
            $message .= ' ' . get_class($variable);
        }

        parent::__construct($message);
    }
}