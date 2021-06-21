<?php
namespace Cherry\Helper;

class Time
{
    public static function ISO8601()
    {
        $date = new \DateTime('now', new \DateTimeZone('UTC'));
        $published = $date->format(\DateTimeInterface::ISO8601);
        return str_replace('+0000', 'Z', $published);
    }

    public static function utc(string $time = 'now')
    {
        $date = new \DateTime($time, new \DateTimeZone('UTC'));
        return $date->format('Y-m-d H:i:s');
    }

    public static function getLocalTime(string $utc, string $format = 'Y-m-d', string $timezone = 'Asia/Shanghai')
    {
        $date = new \DateTime($utc);
        $date->setTimezone(new \DateTimeZone($timezone));
        return $date->format($format);
    }

    public static function utcTimestamp(string $time = 'now')
    {
        $date = new \DateTime('now', new \DateTimeZone('UTC'));
        return $date->getTimestamp();
    }

    public static function delay(int $seconds = 60, string $time = 'now', string $timezone = 'Asia/Shanghai')
    {
        $date = new \DateTime($time, new \DateTimeZone($timezone));
        $date->add(new \DateInterval("PT{$seconds}S"));
        return $date->format('Y-m-d H:i:s');
    }
}