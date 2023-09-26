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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Model\DatahubUploadSession;

use CIHub\Bundle\SimpleRESTAdapterBundle\Model\UploadPartsInterface;
use League\Flysystem\Filesystem;
use Pimcore\Logger;
use Pimcore\Model\Dao\AbstractDao;
use Pimcore\Model\Exception\NotFoundException;
use Pimcore\Tool\Storage;

class Dao extends AbstractDao
{
    protected string $tableName = 'datahub_upload_sessions';

    /**
     * get vote by id.
     *
     * @throws \Exception
     */
    public function getById(string $id): void
    {
        $this->model->setId($id);
        $data = $this->db->fetchAssociative('SELECT * FROM '.$this->tableName.' WHERE id = ?', [$this->model->getId()]);

        if (!$data) {
            throw new NotFoundException('Uload Session with the ID '.$this->model->getId()." doesn't exists");
        }

        $this->assignVariablesToModel($data);
    }

    /**
     * get vote by id.
     *
     * @throws \Exception
     */
    public function hasById(string $id): bool
    {
        $this->model->setId($id);
        $data = $this->db->fetchAssociative('SELECT id FROM '.$this->tableName.' WHERE id = ?', [$this->model->getId()]);

        if (!$data) {
            return false;
        }

        return true;
    }

    /**
     * save vote.
     */
    public function save(): void
    {
        $vars = get_object_vars($this->model);

        $buffer = [];

        $validColumns = $this->getValidTableColumns($this->tableName);

        if (\count($vars)) {
            foreach ($vars as $k => $v) {
                if (!\in_array($k, $validColumns, true)) {
                    continue;
                }

                $getter = 'get'.ucfirst($k);

                if (!\is_callable([$this->model, $getter])) {
                    continue;
                }

                $value = $this->model->$getter();

                if (\is_bool($value)) {
                    $value = (int) $value;
                }
                if (\is_array($value)) {
                    $value = json_encode($value);
                }
                if ($value instanceof UploadPartsInterface) {
                    $value = json_encode($value->toArray());
                }

                $buffer[$k] = $value;
            }
        }

        if ($this->hasById($this->model->getId())) {
            $this->db->update($this->tableName, $buffer, ['id' => $this->model->getId()]);

            return;
        }

        $this->db->insert($this->tableName, $buffer);
    }

    /**
     * delete vote.
     */
    public function delete(): void
    {
        /**
         * @var Filesystem $storage
         */
        $storage = Storage::get('temp');

        foreach ($this->model->getParts() as $part) {
            try {
                $storage->delete($this->model->getTemporaryPartFilename($part->getId()));
            } catch (\Exception $ignore) {
                Logger::error($ignore->getMessage());
            }
        }

        $this->db->delete($this->tableName, ['id' => $this->model->getId()]);
    }
}
