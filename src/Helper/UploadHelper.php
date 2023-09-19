<?php

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Helper;

use CIHub\Bundle\SimpleRESTAdapterBundle\Exception\NotFoundException;
use DateInterval;
use Pimcore\Db;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Uid\Ulid;

final class UploadHelper
{
    public function __construct(private RouterInterface $router)
    {
    }

    public function getSessionResponse(Request $request, string $uuid, string $config, $partSize, int $processed = 0, int $totalParts = 0): array
    {
        return [
            "id" => $uuid,
            "num_parts_processed" => $processed,
            "part_size" => $partSize,
            "endpoints" => [
                "abort" => $this->generateUrl('datahub_rest_endpoints_asset_upload_abort', ['config' => $config, 'id' => $uuid]),
                "commit" => $this->generateUrl('datahub_rest_endpoints_asset_upload_commit', ['config' => $config, 'id' => $uuid]),
                "list_parts" => $this->generateUrl('datahub_rest_endpoints_asset_upload_list_parts', ['config' => $config, 'id' => $uuid]),
                "status" => $this->generateUrl('datahub_rest_endpoints_asset_upload_status', ['config' => $config, 'id' => $uuid]),
                "upload_part" => $this->generateUrl('datahub_rest_endpoints_asset_upload_part', ['config' => $config, 'id' => $uuid])
            ],
            "session_expires_at" => Ulid::fromString($uuid)->getDateTime()->add(new DateInterval('PT1H'))->format('Y-m-d\TH:i:s.u\Z'),
            "total_parts" => $totalParts
        ];
    }

    private function generateUrl(string $route, array $parameters = []): string
    {
        return $this->router->generate($route, $parameters);
    }

    public function createSession(string $id, array $parts = [], int $totalParts = 1)
    {
        Db::get()->executeQuery('INSERT INTO datahub_upload_sessions (session, parts, total_parts) VALUES (?, ?, ?)', [$id, $parts, $totalParts]);
    }

    public function deleteSession(string $id)
    {
        Db::get()->executeQuery('DELETE FROM datahub_upload_sessions WHERE session = ?', [$id]);
    }

    public function hasSession(string $id): bool
    {
        $data = Db::get()->fetchOne('SELECT session FROM datahub_upload_sessions WHERE session = ?', [$id]);
        if ($data) {
            return true;
        }

        return false;
    }

    public function getSession(string $id): array
    {
        $data = Db::get()->fetchAssociative('SELECT * FROM datahub_upload_sessions WHERE session = ?', [$id]);
        if ($data) {
            $data['parts'] = json_decode($data['parts']);
            return $data;
        }

        throw new NotFoundException('Session not found');
    }

    public function getParts(string $id)
    {
        $data = Db::get()->fetchOne('SELECT parts FROM datahub_upload_sessions WHERE session = ?', [$id]);
        if ($data) {
            $data = json_decode($data);
        } else {
            throw new NotFoundException('Session not found');
        }

        return $data;
    }
}
