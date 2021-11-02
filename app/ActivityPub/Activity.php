<?php

namespace Cherry\ActivityPub;

class Activity extends ObjectType
{
    public $actor;
    public $object;
    public $target;
    public $origin;
    public $result;
    public $instrument;

    public function isActorAlias(): bool
    {
        $idHost = parse_url($this->id, PHP_URL_HOST);
        $actorHost = parse_url($this->actor, PHP_URL_HOST);
        return $idHost !== $actorHost;
    }
}
