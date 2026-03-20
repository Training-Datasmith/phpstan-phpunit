<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Php_Unit;

use function dirname;
use function explode;
use function file_get_contents;
use function json_decode;
use Php_Unit\Framework\Test_Case;
use ReflectionClass;
use Reflection_Exception;
class Php_Unit_Version_Detector
{
    public function create_php_unit_version(): Php_Unit_Version
    {
        $file = false;
        $major_version = null;
        $minor_version = null;
        try {
            // uses runtime reflection to reduce unnecessary work while bootstrapping PHPStan.
            // static reflection would need to AST parse and build up reflection for a lot of files otherwise.
            $reflection = new ReflectionClass(Test_Case::class);
            $file = $reflection->get_file_name();
        } catch (Reflection_Exception $e) {
            // PHPUnit might not be installed
        }
        if ($file !== false) {
            $php_unit_root = dirname($file, 3);
            $php_unit_composer = $php_unit_root . '/composer.json';
            $composer_json = @file_get_contents($php_unit_composer);
            if ($composer_json !== false) {
                $json = json_decode($composer_json, true);
                $version = $json['extra']['branch-alias']['dev-main'] ?? null;
                if ($version !== null) {
                    $version_parts = explode('.', $version);
                    $major_version = (int) $version_parts[0];
                    $minor_version = (int) $version_parts[1];
                }
            }
        }
        return new Php_Unit_Version($major_version, $minor_version);
    }
}