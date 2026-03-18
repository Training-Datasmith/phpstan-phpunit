<?php

declare(strict_types=1);

namespace AssertMethod;

use function PHPStan\Testing\assertType;

class Foo
{
    public function inheritedAssertMethodsNarrowType(?string $s): void
    {
        $customAsserter = new class () extends \PHPUnit\Framework\Assert {};
        $customAsserter->assertNotNull($s);
        assertType('string', $s);
    }

}
