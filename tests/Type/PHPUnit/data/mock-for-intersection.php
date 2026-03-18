<?php

declare(strict_types=1);

namespace MockForIntersection;

use function PHPStan\Testing\assertType;

use PHPUnit\Framework\TestCase;

class Foo extends TestCase
{
    public function testFoo(bool $bool): void
    {
        assertType(
            'MockForIntersection\BarInterface&MockForIntersection\FooInterface&PHPUnit\Framework\MockObject\MockObject',
            $this->createMockForIntersectionOfInterfaces([FooInterface::class, BarInterface::class]),
        );
        assertType(
            'MockForIntersection\BarInterface&MockForIntersection\FooInterface&PHPUnit\Framework\MockObject\Stub',
            self::createStubForIntersectionOfInterfaces([FooInterface::class, BarInterface::class]),
        );

        assertType(
            'PHPUnit\Framework\MockObject\MockObject',
            $this->createMockForIntersectionOfInterfaces($bool ? [FooInterface::class, BarInterface::class] : [FooInterface::class]),
        );
        assertType(
            'PHPUnit\Framework\MockObject\MockObject',
            $this->createMockForIntersectionOfInterfaces($bool ? [FooInterface::class] : [BarInterface::class]),
        );
    }

}

interface FooInterface
{
}
interface BarInterface
{
}
