<?php

declare(strict_types=1);

namespace DynamicCallToAssertion;

use PHPUnit\Framework\TestCase;

class Foo extends TestCase
{
    public function testFoo(bool $b): void
    {
        $this->assertTrue($b);
    }

    public function testBar(bool $b): void
    {
        self::assertTrue($b);
    }

    public function foo(): void
    {
        $x = $this->staticFn();
    }

    protected static function staticFn(): bool
    {
        return true;
    }
}
