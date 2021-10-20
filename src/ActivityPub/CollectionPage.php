<?php

namespace Cherry\ActivityPub;

class CollectionPage extends Collection
{
    public $type = 'CollectionPage';
    public $partOf;
    public $next;
    public $prev;
}