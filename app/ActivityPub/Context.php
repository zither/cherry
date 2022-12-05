<?php

namespace Cherry\ActivityPub;

class Context
{
    const OPTION_ACTIVITY_STREAMS = 1;
    const OPTION_SECURITY_V1 = 2;

    public static $contexts = [
        self::OPTION_ACTIVITY_STREAMS => [
            "https://www.w3.org/ns/activitystreams",
            ['sensitive' => 'as:sensitive']
        ],
        self::OPTION_SECURITY_V1 => "https://w3id.org/security/v1",
    ];

    public static function get(int $options = self::OPTION_ACTIVITY_STREAMS)
    {
        $context = [];
        $extraContext = [];
        foreach (self::$contexts as $o => $c) {
            if (!($options & $o)) {
                continue;
            }
            if (is_string($c)) {
                $context[] = $c;
            } else if (is_array($c)) {
                foreach ($c as $v) {
                    if (is_string($v)) {
                        $context[] = $v;
                    } else if (is_array($v)) {
                        $extraContext = array_merge($extraContext, $v);
                    }
                }
            }
        }
        if (empty($context)) {
            return $extraContext;
        }
        if (!empty($extraContext)) {
            $context[] = $extraContext;
        }
        return $context;
    }

    public static function set(array $object, int $options = self::OPTION_ACTIVITY_STREAMS): array
    {
        $context = self::get($options);
        $object = array_merge(['@context' => $context], $object);
        return $object;
    }
}
