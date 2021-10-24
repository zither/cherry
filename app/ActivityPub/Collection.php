<?php

namespace Cherry\ActivityPub;

class Collection extends ObjectType
{
    public $type = 'Collection';
    public $totalItems;
    public $current;
    public $first;
    public $last;
    public $items;
}