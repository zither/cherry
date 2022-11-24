<?php

namespace Cherry\Controller;

use Medoo\Medoo;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

class BaseController
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    protected function getPostParam(ServerRequestInterface $request, string $key, $default = null)
    {
        $params = $request->getParsedBody();
        return $params[$key] ?? $default;
    }

    protected function getQueryParam(ServerRequestInterface $request, string $key, $default = null)
    {
        $queries = $request->getQueryParams();
        return $queries[$key] ?? $default;
    }

    protected function db(): Medoo
    {
        return $this->container->get(Medoo::class);
    }

    protected function adminProfile(array $columns = []): array
    {
        $defaultColumns = [
            'id',
            'actor',
            'name',
            'preferred_name',
            'account',
            'url',
            'avatar',
            'summary',
        ];
        $extraColumns = [
            'type',
            'inbox',
            'outbox',
            'following',
            'followers',
            'likes',
            'featured',
            'shared_inbox',
            'public_key'
        ];
        if (empty($columns)) {
            $selectedColumns = $defaultColumns;
        } else if(count($columns) === 1 && $columns[0] === '*') {
            $selectedColumns = array_merge($defaultColumns, $extraColumns);
        } else {
            $selectedColumns = $columns;
        }
        return $this->db()->get('profiles', $selectedColumns, ['id' => CHERRY_ADMIN_PROFILE_ID]);
    }
}