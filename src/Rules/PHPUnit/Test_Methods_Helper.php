<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Php_Unit;

use function array_key_exists;
use Php_Stan\Analyser\Scope;
use Php_Stan\Better_Reflection\Reflection\ReflectionMethod;
use Php_Stan\Php_Doc\Resolved_Php_Doc_Block;
use Php_Stan\Reflection\Class_Reflection;
use Php_Stan\Reflection\Method_Reflection;
use Php_Stan\Type\File_Type_Mapper;
use Php_Unit\Framework\Test_Case;
use function str_starts_with;
use function strtolower;
final class Test_Methods_Helper
{
    private File_Type_Mapper $file_type_mapper;
    private Php_Unit_Version $php_unit_version;
    /** @var array<string, array<ReflectionMethod>> */
    private array $method_cache = [];
    public function __construct(File_Type_Mapper $file_type_mapper, Php_Unit_Version $php_unit_version)
    {
        $this->file_type_mapper = $file_type_mapper;
        $this->php_unit_version = $php_unit_version;
    }
    public function get_test_method_reflection(Class_Reflection $class_reflection, Method_Reflection $method_reflection, Scope $scope): ?ReflectionMethod
    {
        foreach ($this->get_test_methods($class_reflection, $scope) as $test_method) {
            if ($test_method->get_name() === $method_reflection->get_name()) {
                return $test_method;
            }
        }
        return null;
    }
    /**
     * @return array<ReflectionMethod>
     */
    public function get_test_methods(Class_Reflection $class_reflection, Scope $scope): array
    {
        $class_name = $class_reflection->get_name();
        if (array_key_exists($class_name, $this->method_cache)) {
            return $this->method_cache[$class_name];
        }
        if (!$class_reflection->is(Test_Case::class)) {
            return $this->method_cache[$class_name] = [];
        }
        $test_methods = [];
        foreach ($class_reflection->get_native_reflection()->get_better_reflection()->get_immediate_methods() as $reflection_method) {
            if (!$reflection_method->is_public()) {
                continue;
            }
            if (str_starts_with(strtolower($reflection_method->get_name()), 'test')) {
                $test_methods[] = $reflection_method;
                continue;
            }
            $doc_comment = $reflection_method->get_doc_comment();
            if ($doc_comment !== null) {
                $method_php_doc = $this->file_type_mapper->get_resolved_php_doc($scope->get_file(), $class_name, $scope->is_in_trait() ? $scope->get_trait_reflection()->get_name() : null, $reflection_method->get_name(), $doc_comment);
                if ($this->has_test_annotation($method_php_doc)) {
                    $test_methods[] = $reflection_method;
                    continue;
                }
            }
            if ($this->php_unit_version->supports_test_attribute()->no()) {
                continue;
            }
            $test_attributes = $reflection_method->get_attributes_by_name('PHPUnit\Framework\Attributes\Test');
            // @phpstan-ignore argument.type
            if ($test_attributes === []) {
                continue;
            }
            $test_methods[] = $reflection_method;
        }
        return $this->method_cache[$class_name] = $test_methods;
    }
    private function has_test_annotation(?Resolved_Php_Doc_Block $php_doc): bool
    {
        if ($php_doc === null) {
            return false;
        }
        $php_doc_nodes = $php_doc->get_php_doc_nodes();
        foreach ($php_doc_nodes as $doc_node) {
            $tags = $doc_node->get_tags_by_name('@test');
            if ($tags !== []) {
                return true;
            }
        }
        return false;
    }
}