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

    /**
     * @var array
     */
    public $signature;

    public function isActorAlias(): bool
    {
        $idHost = parse_url($this->id, PHP_URL_HOST);
        $actorHost = parse_url($this->actor, PHP_URL_HOST);
        if ($idHost !== $actorHost) {
            return true;
        }
        if (!empty($this->signature['creator'])) {
            $creatorHost = parse_url($this->signature['creator'], PHP_URL_HOST);
            if ($creatorHost !== $idHost || $creatorHost !== $actorHost) {
                return true;
            }
        }

        return false;
    }

    public static function rawObjectId(array $activity): string
    {
        if (!isset($activity['object'])) {
            return '';
        }
        if (is_string($activity['object'])) {
            return $activity['object'];
        }
        if (is_array($activity['object']) && isset($activity['object']['id'])) {
            return $activity['object']['id'];
        }
        return '';
    }
}
