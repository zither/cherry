<?php

namespace Cherry\Test\Helper;

use Cherry\Helper\Time;
use PHPUnit\Framework\TestCase;

class TimeTest extends TestCase
{
    public function testGetTimeZoneOffsetWithoutParam()
    {
        Time::$defaultTimeZone = null;
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
}