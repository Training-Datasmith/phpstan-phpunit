<?php

declare(strict_types=1);

/**
 * Example: Using phpstan-phpunit to catch common PHPUnit mistakes.
 *
 * Install:
 *   composer require --dev phpstan/phpstan-phpunit
 *
 * The extension is auto-loaded via phpstan/extension-installer.
 * To enable the strict assertion rules, add to phpstan.neon:
 *
 *   includes:
 *     - vendor/phpstan/phpstan-phpunit/rules.neon
 */

namespace App\Tests;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    // --- Mock type inference ---

    public function testMockReturnType(): void
    {
        // PHPStan knows $mock is MockObject&SomeService, not just MockObject.
        // This means calling SomeService methods is type-safe.
        $mock = $this->createMock(SomeService::class);
        $mock->method('process')->willReturn('result');

        // $result is inferred as string, not mixed.
        $result = $mock->process('input');
    }


    // --- Assert type narrowing ---

    public function testAssertNarrowing(mixed $value): void
    {
        $this->assertInstanceOf(\DateTimeImmutable::class, $value);

        // After assertInstanceOf, PHPStan knows $value is DateTimeImmutable.
        $timestamp = $value->getTimestamp(); // No "method not found on mixed" error.
    }


    // --- Rules: prefer specific assertions ---

    public function testBetterAssertions(): void
    {
        $items = [1, 2, 3];

        // PHPStan rule (rules.neon): suggest assertCount() instead:
        // $this->assertSame(3, count($items));  // flagged
        $this->assertCount(3, $items); // correct

        // PHPStan rule: suggest assertTrue() instead:
        // $this->assertSame(true, $items !== []);  // flagged
        $this->assertTrue($items !== []); // correct

        // PHPStan rule: suggest assertNull() instead:
        // $this->assertSame(null, $result);  // flagged
        $result = null;
        $this->assertNull($result); // correct
    }


    // --- Data providers must be static (PHPUnit 10+) ---

    /**
     * @dataProvider provideValues
     */
    public function testWithDataProvider(int $input, int $expected): void
    {
        $this->assertSame($expected, $input * 2);
    }

    /**
     * PHPStan rule: data providers must be static in PHPUnit 10+.
     *
     * @return array<array{int, int}>
     */
    public static function provideValues(): array
    {
        return [
            [1, 2],
            [2, 4],
            [3, 6],
        ];
    }
}

class SomeService
{
    public function process(string $input): string
    {
        return strtoupper($input);
    }
}
