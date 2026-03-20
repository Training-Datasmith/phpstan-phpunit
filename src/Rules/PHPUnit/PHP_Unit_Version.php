<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Php_Unit;

use Php_Stan\Trinary_Logic;
class Php_Unit_Version
{
    private ?int $major_version;
    private ?int $minor_version;
    public function __construct(?int $major_version, ?int $minor_version)
    {
        $this->major_version = $major_version;
        $this->minor_version = $minor_version;
    }
    public function supports_data_provider_attribute(): Trinary_Logic
    {
        if ($this->major_version === null) {
            return Trinary_Logic::create_maybe();
        }
        return Trinary_Logic::create_from_boolean($this->major_version >= 10);
    }
    public function supports_test_attribute(): Trinary_Logic
    {
        if ($this->major_version === null) {
            return Trinary_Logic::create_maybe();
        }
        return Trinary_Logic::create_from_boolean($this->major_version >= 10);
    }
    public function requires_static_data_providers(): Trinary_Logic
    {
        if ($this->major_version === null) {
            return Trinary_Logic::create_maybe();
        }
        return Trinary_Logic::create_from_boolean($this->major_version >= 10);
    }
    public function supports_named_arguments_in_data_provider(): Trinary_Logic
    {
        if ($this->major_version === null) {
            return Trinary_Logic::create_maybe();
        }
        return Trinary_Logic::create_from_boolean($this->major_version >= 11);
    }
    public function requires_phpversion_attribute_with_operator(): Trinary_Logic
    {
        if ($this->major_version === null) {
            return Trinary_Logic::create_maybe();
        }
        return Trinary_Logic::create_from_boolean($this->major_version >= 13);
    }
    public function deprecates_phpversion_attribute_without_operator(): Trinary_Logic
    {
        return $this->min_version(12, 4);
    }
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