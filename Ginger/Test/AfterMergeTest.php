<?php

namespace Ginger\Test;
use PHPUnit\Framework\TestCase;

class AfterMergeTest extends TestCase
{
    function testGetClassBankConfig()
    {
        self::assertFileExists((__DIR__).'/../Lib/Bankconfig.php', 'The class-ginger-bankconfig not exists, merge unsuccessful');
    }

    function testGetClassHelper()
    {
        self::assertFileExists((__DIR__).'/../Lib/Helper.php', 'The class-ginger-helper not exists, merge unsuccessful');
    }
}