<?php

namespace Cherry\Test\Helper;

use Cherry\Helper\Time;
use PHPUnit\Framework\TestCase;

class TimeTest extends TestCase
{
    public function testGetTimeZoneOffsetWithoutParam()
    {
        $offset = Time::getTimeZoneOffset();
        $this->assertEquals('+00:00', $offset);
        Time::$defaultTimeZone = 'Asia/ShangHai';
        $offset = Time::getTimeZoneOffset();
        $this->assertEquals('+08:00', $offset);
    }

    public function testGetTimeZoneOffsetWithParam()
    {
        $offset = Time::getTimeZoneOffset('Asia/ShangHai');
        $this->assertEquals('+08:00', $offset);
    }

    public function testUTCToLocalTime()
    {
        Time::$defaultTimeZone = 'Asia/Shanghai';
        $UTCTime = '2021-01-01T00:00:00Z';
        $localTime = Time::UTCToLocalTime($UTCTime);
        $this->assertEquals('2021-01-01 08:00:00', $localTime);
    }

    public function testRelativeUnit()
    {
        $targetTime = new \DateTime('-1 month');
        $this->assertEquals([], Time::relativeUnit($targetTime->format('Y-m-d H:i:s')));
        $targetTime = new \DateTime('-1 day');
        $this->assertEquals(
            ['time' => '1', 'unit' => 'day'],
            Time::relativeUnit($targetTime->format('Y-m-d H:i:s'), '', 'day')
        );
        $targetTime = new \DateTime('-2 hours');
        $this->assertEquals(
            ['time' => 2, 'unit' => 'hour'],
            Time::relativeUnit($targetTime->format('Y-m-d H:i:s'))
        );

        $targetTime = new \DateTime('-20 minutes');
        $this->assertEquals(
            ['time' => 20, 'unit' => 'short_minute'],
            Time::relativeUnit($targetTime->format('Y-m-d H:i:s'), 'short_')
        );

        $targetTime = new \DateTime('-30 seconds');
        $this->assertEquals(
            ['time' => 30, 'unit' => 'second'],
            Time::relativeUnit($targetTime->format('Y-m-d H:i:s'))
        );
        $targetTime = new \DateTime('-2 years');
        $this->assertEquals(
            [],
            Time::relativeUnit($targetTime->format('Y-m-d H:i:s'))
        );
        $targetTime = new \DateTime('-2 years -7 hours');
        $this->assertEquals(
            [],
            Time::relativeUnit($targetTime->format('Y-m-d H:i:s'))
        );

    }
}
