<?php

namespace Cherry\ActivityPub;

class OrderedCollection extends Collection
{
    public $type = 'OrderedCollection';
    /**
     * @var array
     */
    public $orderedItems;
}