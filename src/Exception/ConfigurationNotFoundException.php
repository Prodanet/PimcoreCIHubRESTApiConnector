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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Exception;

use Symfony\Component\HttpFoundation\Response;

final class ConfigurationNotFoundException extends \RuntimeException implements EndpointExceptionInterface
{
    public function __construct(string $configName, int $code = Response::HTTP_NOT_FOUND)
    {
        parent::__construct(sprintf("Invalid or unknown config name '%s'.", $configName), $code);
    }
}
