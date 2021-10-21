<?php
namespace Cherry\Helper;

use DateTime;
use DateTimeZone;
use DateInterval;
use DateTimeInterface;

class Time
{
    public static $defaultTimeZone = 'UTC';

    public static function UTCTimeISO8601()
    {
        $date = new DateTime('now', new DateTimeZone('UTC'));
        $published = $date->format(DateTimeInterface::ISO8601);
        return str_replace('+0000', 'Z', $published);
    }

    public static function UTCToLocalTime(string $time = 'now', string $format='Y-m-d H:i:s')
    {
        $date = new DateTime($time, new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone(self::$defaultTimeZone));
        return $date->format($format);
    }

    public static function getLocalTime(string $time = 'now', string $format = 'Y-m-d H:i:s')
    {
        $date = new DateTime($time, new DateTimeZone(self::$defaultTimeZone));
        return $date->format($format);
    }

    public static function delay(int $seconds = 60, string $time = 'now')
    {
        $date = new DateTime($time, new DateTimeZone(self::$defaultTimeZone));
        $date->add(new DateInterval("PT{$seconds}S"));
        return $date->format('Y-m-d H:i:s');
    }
}