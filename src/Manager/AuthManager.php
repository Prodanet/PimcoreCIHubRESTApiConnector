<?php

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Manager;

use CIHub\Bundle\SimpleRESTAdapterBundle\Exception\AccessDeniedException;
use CIHub\Bundle\SimpleRESTAdapterBundle\Exception\ConfigurationNotFoundException;
use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use CIHub\Bundle\SimpleRESTAdapterBundle\Repository\DataHubConfigurationRepository;
use Doctrine\DBAL\Exception;
use Pimcore;
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
    /**
     * @var string
     */
    protected string $config;
    private Request $request;

    public function __construct(private readonly DataHubConfigurationRepository $configRepository,
                                private readonly RequestStack $requestStack) {
        $this->request = $this->requestStack->getMainRequest();
        $this->config = $this->request->get('config');
    }

    public function isAllowed(Asset $asset, string $type, User $user) {
        $configuration = $this->getDataHubConfiguration();
        $reader = new ConfigReader($configuration->getConfiguration());
        $isAllowed = false;
        foreach ($reader->getPermissions() as $permission) {
            $permissionType = $permission[$type] ?? false;
            if($permission['id'] === $user->getId() && $permissionType === true) {
                $isAllowed = true;
            }
        }

        $event = new ElementEvent($asset, ['isAllowed' => $isAllowed, 'permissionType' => $type, 'user' => $user]);
        Pimcore::getEventDispatcher()->dispatch($event, ElementEvents::ELEMENT_PERMISSION_IS_ALLOWED);

        return (bool) $event->getArgument('isAllowed');
    }

    /**
     * @throws Exception
     */
    public function checkAuthentication(): void
    {
        $user = $this->getUserByToken();
        if (!$user) {
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

    protected function getUserByToken(): ?User
    {
        $configuration = $this->getDataHubConfiguration();
        $reader = new ConfigReader($configuration->getConfiguration());

        if (!$this->request->headers->has('Authorization')
            || !str_starts_with($this->request->headers->get('Authorization'), 'Bearer ')) {
            throw new AccessDeniedException();
        }

        // skip beyond "Bearer "
        $authorizationHeader = substr($this->request->headers->get('Authorization'), 7);

        $db = Db::get();
        $userId = $db->fetchOne('SELECT userId FROM `users_datahub_config` WHERE JSON_UNQUOTE(JSON_EXTRACT(data, \'$.apikey\')) = ?', [$authorizationHeader]);
        foreach ($reader->getPermissions() as $permission) {
            if($permission['id'] === $userId) {
                return User::getById($userId);
            }
        }

        throw new AuthenticationException('Failed to authenticate with username and token');
    }

    private static function isValidUser(?User $user): bool
    {
        return $user instanceof User && $user->isActive() && $user->getId();
    }

    protected function getDataHubConfiguration(): Configuration
    {
        $configuration = $this->configRepository->findOneByName($this->config);

        if (!$configuration instanceof Configuration) {
            throw new ConfigurationNotFoundException($this->config);
        }

        return $configuration;
    }
}
