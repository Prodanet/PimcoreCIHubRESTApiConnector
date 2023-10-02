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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Manager;

use CIHub\Bundle\SimpleRESTAdapterBundle\Exception\AccessDeniedException;
use CIHub\Bundle\SimpleRESTAdapterBundle\Exception\ConfigurationNotFoundException;
use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use CIHub\Bundle\SimpleRESTAdapterBundle\Repository\DataHubConfigurationRepository;
use Doctrine\DBAL\Exception;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Db;
use Pimcore\Event\ElementEvents;
use Pimcore\Event\Model\ElementEvent;
use Pimcore\Model\Asset;
use Pimcore\Model\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class AuthManager
{
    private string $config;

    private Request $request;

    public function __construct(
        private DataHubConfigurationRepository $dataHubConfigurationRepository,
        private RequestStack $requestStack
    ) {
        $this->request = $this->requestStack->getMainRequest();
        $this->config = $this->request->get('config');
    }

    public function isAllowed(Asset $asset, string $type, User $user): bool
    {
        $configuration = $this->getDataHubConfiguration();
        $configReader = new ConfigReader($configuration->getConfiguration());
        $isAllowed = false;
        foreach ($configReader->getPermissions() as $permission) {
            $permissionType = $permission[$type] ?? false;
            if ($permission['id'] === $user->getId() && true === $permissionType) {
                $isAllowed = true;
            }
        }

        $elementEvent = new ElementEvent($asset, ['isAllowed' => $isAllowed, 'permissionType' => $type, 'user' => $user]);
        \Pimcore::getEventDispatcher()->dispatch($elementEvent, ElementEvents::ELEMENT_PERMISSION_IS_ALLOWED);

        return (bool) $elementEvent->getArgument('isAllowed');
    }

    public function checkAuthentication(): void
    {
        $user = $this->getUserByToken();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }
    }

    /**
     * @throws Exception
     */
    public function authenticate(): User
    {
        $this->checkAuthentication();
        $user = $this->getUserByToken();
        if (self::isValidUser($user)) {
            return $user;
        }

        throw new AuthenticationException('Failed to authenticate with username and token');
    }

    private function getUserByToken(): ?User
    {
        $configuration = $this->getDataHubConfiguration();
        $configReader = new ConfigReader($configuration->getConfiguration());

        if (!$this->request->headers->has('Authorization')
            || !str_starts_with($this->request->headers->get('Authorization'), 'Bearer ')) {
            throw new AccessDeniedException();
        }

        // skip beyond "Bearer "
        $authorizationHeader = mb_substr($this->request->headers->get('Authorization'), 7);

        $db = Db::get();
        $userId = $db->fetchOne('SELECT userId FROM `users_datahub_config` WHERE JSON_UNQUOTE(JSON_EXTRACT(data, \'$.apikey\')) = ?', [$authorizationHeader]);
        foreach ($configReader->getPermissions() as $permission) {
            if ($permission['id'] === $userId) {
                return User::getById($userId);
            }
        }

        throw new AuthenticationException('Failed to authenticate with username and token');
    }

    private function isValidUser(?User $user): bool
    {
        return $user instanceof User && $user->isActive() && $user->getId();
    }

    private function getDataHubConfiguration(): Configuration
    {
        $configuration = $this->dataHubConfigurationRepository->findOneByName($this->config);

        if (!$configuration instanceof Configuration) {
            throw new ConfigurationNotFoundException($this->config);
        }

        return $configuration;
    }
}
