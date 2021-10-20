<?php
namespace Cherry\ActivityPub;

use ReflectionObject;
use ReflectionProperty;

class ObjectType extends AbstractType
{
    const PUBLIC_COLLECTION = 'https://www.w3.org/ns/activitystreams#Public';

    public $context = [
        "https://www.w3.org/ns/activitystreams",
        ['sensitive' => 'as:sensitive']
    ];

    public $type = 'Object';

    /**
     * @var string
     */
    public $id;

    public $name;
    public $attributedTo;
    public $content;
    public $summary;
    public $url;
    public $mediaType;
    public $attachment;
    public $to;
    public $bto;
    public $cc;
    public $bcc;
    public $audience;
    public $published;
    public $inReplyTo;
    public $icon;
    public $generator;
    public $image;
    public $location;
    public $preview;
    public $replies;
    public $tag;
    public $updated;
    public $startTime;
    public $endTime;
    public $duration;
    public $sensitive;

    public function isPublic(array $audiences = null): bool
    {
        if (is_null($audiences)) {
            $audiences = [$this->to, $this->cc, $this->audience];
        }
        foreach ($audiences as $v) {
            if (is_null($v)) {
                continue;
            }
            if (is_string($v)) {
                if ($v === static::PUBLIC_COLLECTION) {
                    return true;
                }
            }
            if (is_array($v)) {
                return $this->isPublic($v);
            }
        }
        return false;
    }

    public function audiences()
    {
        $audiences = [$this->to, $this->cc, $this->audience, $this->bto, $this->bcc];
        $receivers = [];
        foreach ($audiences as $v) {
            if (empty($v)) {
                continue;
            }
            if (is_string($v)) {
                $receivers[] = $v;
            } else if (is_array($v)) {
                $receivers =  array_merge($receivers, $v);
            }
        }
        return $receivers;
    }

    public function toArray()
    {
        $reflection = new ReflectionObject($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED);
        $array = [];
        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }
            $name = $property->getName();
            if (is_null($this->{$name})) {
                continue;
            }
            $array[$name] = $this->{$name};
        }
        return $array;
    }

    public function isReply()
    {
        return !empty($this->inReplyTo);
    }

    public function getStringAttribute(string $key)
    {
        if (isset($this->$key) && is_string($this->$key)) {
            return $this->$key;
        }
        return '';
    }

    public function lowerType()
    {
        return strtolower($this->type);
    }
}