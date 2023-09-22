<?php

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Model\DatahubUploadSession;

use CIHub\Bundle\SimpleRESTAdapterBundle\Model\UploadPartsInterface;
use Exception;
use League\Flysystem\Filesystem;
use Pimcore\Logger;
use Pimcore\Model\Dao\AbstractDao;
use Pimcore\Model\Exception\NotFoundException;
use Pimcore\Tool\Storage;
use function Clue\StreamFilter\fun;

class Dao extends AbstractDao
{
    protected string $tableName = 'datahub_upload_sessions';
    /**
     * get vote by id
     *
     * @throws Exception
     */
    public function getById(string $id): void
    {
        $this->model->setId($id);
        $data = $this->db->fetchAssociative('SELECT * FROM ' . $this->tableName . ' WHERE id = ?', [$this->model->getId()]);

        if (!$data) {
            throw new NotFoundException("Uload Session with the ID " . $this->model->getId() . " doesn't exists");
        }

        $this->assignVariablesToModel($data);
    }

    /**
     * get vote by id
     *
     * @throws Exception
     */
    public function hasById(string $id): bool
    {
        $this->model->setId($id);
        $data = $this->db->fetchAssociative('SELECT id FROM ' . $this->tableName . ' WHERE id = ?', [$this->model->getId()]);

        if (!$data) {
            return false;
        }

        return true;
    }

    /**
     * save vote
     */
    public function save(): void
    {
        $vars = get_object_vars($this->model);

        $buffer = [];

        $validColumns = $this->getValidTableColumns($this->tableName);

        if (count($vars)) {

            foreach ($vars as $k => $v) {
                if (!in_array($k, $validColumns)) {
                    continue;
                }

                $getter = "get" . ucfirst($k);

                if (!is_callable([$this->model, $getter])) {
                    continue;
                }

                $value = $this->model->$getter();

                if (is_bool($value)) {
                    $value = (int)$value;
                }
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                if ($value instanceof UploadPartsInterface) {
                    $value = json_encode($value->toArray());
                }

                $buffer[$k] = $value;
            }
        }

        if ($this->hasById($this->model->getId())) {
            $this->db->update($this->tableName, $buffer, ["id" => $this->model->getId()]);
            return;
        }

        $this->db->insert($this->tableName, $buffer);
    }

    /**
     * delete vote
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
            } catch (Exception $ignore) {
                Logger::error($ignore->getMessage());
            }
        }

        $this->db->delete($this->tableName, ["id" => $this->model->getId()]);
    }

}
