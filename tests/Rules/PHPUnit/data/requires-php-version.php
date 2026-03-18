<?php

declare(strict_types=1);

namespace RequiresPhpVersion;

use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;

class DeprecatedVersionFormat extends TestCase
{
    #[RequiresPhp('8.0')]
    public function testDeprecatedFormat(): void
    {

    }
}

class AllGoodTest extends TestCase
{
    #[RequiresPhp('>=8.0')]
    public function testHappyPath(): void
    {

    }
}
