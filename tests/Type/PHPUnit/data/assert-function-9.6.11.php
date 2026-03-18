<?php

declare(strict_types=1);

namespace AssertFunction;

use function PHPStan\Testing\assertType;
use function PHPUnit\Framework\assertObjectHasProperty;

class Foo
{
    public function objectHasProperty(object $a): void
    {
        assertObjectHasProperty('property', $a);
        assertType('object&hasProperty(property)', $a);
    }

}
