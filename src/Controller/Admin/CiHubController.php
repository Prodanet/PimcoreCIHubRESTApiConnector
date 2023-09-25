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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Controller\Admin;

use CIHub\Bundle\SimpleRESTAdapterBundle\Repository\DataHubConfigurationRepository;
use Doctrine\DBAL\Exception;
use Pimcore\Controller\Traits\JsonHelperTrait;
use Pimcore\Controller\UserAwareController;
use Pimcore\Db;
use Pimcore\Model\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/ci-hub')]
class CiHubController extends UserAwareController
{
    use JsonHelperTrait;

    #[Route('/config/list', name: 'admin_ci_hub_user_config_list', options: ['expose' => true])]
    public function list(DataHubConfigurationRepository $configRepository): Response
    {
        $list = $configRepository->all();

        return $this->jsonResponse([
            'data' => array_keys($list),
            'success' => \count($list) > 0,
        ]);
    }

    /**
     * @throws Exception
     */
    #[Route('/config/update', name: 'admin_ci_hub_user_config_update', options: ['expose' => true])]
    public function update(Request $request): Response
    {
        /** @var User|User\Role|null $user */
        $user = User\UserRole::getById($request->request->getInt('id'));
        $currentUserIsAdmin = $this->getPimcoreUser()->isAdmin();

        if (!$user) {
            throw $this->createNotFoundException();
        }

        if ($user instanceof User && $user->isAdmin() && !$currentUserIsAdmin) {
            throw $this->createAccessDeniedHttpException('Only admin users are allowed to modify admin users');
        }

        if ($request->get('data')) {
            $db = Db::get();

            $data = $db->fetchOne('SELECT data FROM users_datahub_config WHERE userId = ' . $user->getId());
            if ($data) {
                $db->update('users_datahub_config', [
                    'data' => $request->get('data'),
                ], ['userId' => $user->getId()]);
            } else {
                $db->insert('users_datahub_config', [
                    'data' => $request->get('data'),
                    'userId' => $user->getId(),
                ]);
            }
        }

        return $this->jsonResponse(['success' => true]);
    }

    /**
     * @throws Exception
     */
    #[Route('/config', name: 'admin_ci_hub_user_config', options: ['expose' => true])]
    public function get(Request $request): Response
    {
        $userId = (int)$request->get('id');
        if ($userId < 1) {
            throw $this->createNotFoundException();
        }

        $user = User::getById($userId);

        if (!$user) {
            throw $this->createNotFoundException();
        }

        if ($user->isAdmin() && !$this->getPimcoreUser()->isAdmin()) {
            throw $this->createAccessDeniedHttpException('Only admin users are allowed to modify admin users');
        }

        $data = Db::get()->fetchOne('SELECT data FROM users_datahub_config WHERE userId = ' . $user->getId());

        if (!$data) {
            $data = '{}';
        }

        return $this->jsonResponse(json_decode($data));
    }
}
