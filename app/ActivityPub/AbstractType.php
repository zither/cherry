<?php

namespace Cherry\ActivityPub;

use InvalidArgumentException;

abstract class AbstractType
{
    protected static $requiredAttributes = [];

    final public function __construct()
    {
    }

    public static function createFromArray(array $attributes)
    {
        foreach (static::$requiredAttributes as $attribute) {
            if (!isset($attributes[$attribute])) {
                throw new InvalidArgumentException("Attribute $attribute  required");
            }
        }
        $instance = new static;
        foreach ($attributes as $k => $v) {
            $instance->set($k, $v);
        }
        return $instance;
    }

    public function set(string $attributeName, $value)
    {
        //if (property_exists($this, $attributeName)) {
            $this->{$attributeName} = $value;
        //}
    }
}