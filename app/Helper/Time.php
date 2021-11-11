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

    public static function getTimeZoneOffset(string $timezone = null): string
    {
        $timezone = $timezone ?: self::$defaultTimeZone;
        $time = new DateTime('now', new DateTimeZone($timezone));
        return $time->format('P');
    }

    public static function relativeUnit(string $time, string $prefix = '', string $startUnit = 'hour')
    {
        $targetTime = new DateTime($time);
        $now = new DateTime('now');
        $diff = $now->diff($targetTime);
        $formats = [
            '%y' => 'year',
            '%m' => 'month',
            '%a' => 'day',
            '%h' => 'hour',
            '%i' => 'minute',
            '%s' => 'second'
        ];
        $started = false;
        $time = [];
        foreach ($formats as $format => $unit) {
            $num = $diff->format($format);
            if ($started === false) {
                if ($unit !== $startUnit) {
                    if ($num) {
                        break;
                    }
                    continue;
                }
                $started = true;
            }
            if ($num) {
                $time = [
                    'time' => (int)$num,
                    'unit' => $prefix . $unit
                ];
                break;
            }
        }
        return $time;
    }
}
