<?php

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Flyststem\Exception;

use CIHub\Bundle\SimpleRESTAdapterBundle\Exception\EndpointExceptionInterface;
use Exception;

class FileNotFoundException extends Exception implements EndpointExceptionInterface
{

}
