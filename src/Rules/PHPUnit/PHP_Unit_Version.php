<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Php_Unit;

use Php_Stan\Trinary_Logic;
class Php_Unit_Version
{
    private ?int $major_version;
    private ?int $minor_version;

    /**
     * @param int|null $major_version The major version of the installed PHPUnit (e.g., 10), or null if unknown
     * @param int|null $minor_version The minor version of the installed PHPUnit (e.g., 5), or null if unknown
     */
    public function __construct(?int $major_version, ?int $minor_version)
    {
        $this->major_version = $major_version;
        $this->minor_version = $minor_version;
    }

    /**
     * Whether the installed PHPUnit version supports the `#[DataProvider]` attribute (PHPUnit >= 10).
     *
     * @return Trinary_Logic YES for PHPUnit 10+, NO for older versions, MAYBE if version is unknown
     */
    public function supports_data_provider_attribute(): Trinary_Logic
    {
        if ($this->major_version === null) {
            return Trinary_Logic::create_maybe();
        }
        return Trinary_Logic::create_from_boolean($this->major_version >= 10);
    }
    /**
     * Whether the installed PHPUnit version supports the `#[Test]` attribute (PHPUnit >= 10).
     *
     * @return Trinary_Logic YES for PHPUnit 10+, NO for older versions, MAYBE if version is unknown
     */
    public function supports_test_attribute(): Trinary_Logic
    {
        if ($this->major_version === null) {
            return Trinary_Logic::create_maybe();
        }
        return Trinary_Logic::create_from_boolean($this->major_version >= 10);
    }
    /**
     * Whether the installed PHPUnit version requires data providers to be static (PHPUnit >= 10).
     *
     * @return Trinary_Logic YES for PHPUnit 10+, NO for older versions, MAYBE if version is unknown
     */
    public function requires_static_data_providers(): Trinary_Logic
    {
        if ($this->major_version === null) {
            return Trinary_Logic::create_maybe();
        }
        return Trinary_Logic::create_from_boolean($this->major_version >= 10);
    }
    /**
     * Whether the installed PHPUnit version supports named arguments in data providers (PHPUnit >= 11).
     *
     * @return Trinary_Logic YES for PHPUnit 11+, NO for older versions, MAYBE if version is unknown
     */
    public function supports_named_arguments_in_data_provider(): Trinary_Logic
    {
        if ($this->major_version === null) {
            return Trinary_Logic::create_maybe();
        }
        return Trinary_Logic::create_from_boolean($this->major_version >= 11);
    }
    /**
     * Whether the installed PHPUnit version requires `#[RequiresPhp]` to include a comparison operator (PHPUnit >= 13).
     *
     * @return Trinary_Logic YES for PHPUnit 13+, NO for older versions, MAYBE if version is unknown
     */
    public function requires_phpversion_attribute_with_operator(): Trinary_Logic
    {
        if ($this->major_version === null) {
            return Trinary_Logic::create_maybe();
        }
        return Trinary_Logic::create_from_boolean($this->major_version >= 13);
    }
    /**
     * Whether the installed PHPUnit version has deprecated `#[RequiresPhp]` without an operator (PHPUnit >= 12.4).
     *
     * @return Trinary_Logic YES for PHPUnit 12.4+, NO for older versions, MAYBE if version is unknown
     */
    public function deprecates_phpversion_attribute_without_operator(): Trinary_Logic
    {
        return $this->min_version(12, 4);
    }
    /**
     * Returns YES if the installed PHPUnit version is at least the given major.minor, NO if below, MAYBE if unknown.
     *
     * @param int $major Minimum required major version
     * @param int $minor Minimum required minor version when major version matches exactly
     *
     * @return Trinary_Logic Result of the version comparison
     */
    private function min_version(int $major, int $minor): Trinary_Logic
    {
        if ($this->major_version === null || $this->minor_version === null) {
            return Trinary_Logic::create_maybe();
        }
        if ($this->major_version > $major) {
            return Trinary_Logic::create_yes();
        }
        if ($this->major_version === $major && $this->minor_version >= $minor) {
            return Trinary_Logic::create_yes();
        }
        return Trinary_Logic::create_no();
    }
}