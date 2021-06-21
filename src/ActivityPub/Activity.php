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
}