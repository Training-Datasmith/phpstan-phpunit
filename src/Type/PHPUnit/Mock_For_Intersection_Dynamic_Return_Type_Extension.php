<?php

declare (strict_types=1);
namespace Php_Stan\Type\Php_Unit;

use function count;
use Php_Parser\Node\Arg;
use Php_Parser\Node\Expr\Method_Call;
use Php_Parser\Node\Expr\Static_Call;
use Php_Stan\Analyser\Scope;
use Php_Stan\Reflection\Method_Reflection;
use Php_Stan\Type\Dynamic_Method_Return_Type_Extension;
use Php_Stan\Type\Dynamic_Static_Method_Return_Type_Extension;
use Php_Stan\Type\Object_Type;
use Php_Stan\Type\Type;
use Php_Stan\Type\Type_Combinator;
use Php_Unit\Framework\Mock_Object\Mock_Object;
use Php_Unit\Framework\Mock_Object\Stub;
use Php_Unit\Framework\Test_Case;
class Mock_For_Intersection_Dynamic_Return_Type_Extension implements Dynamic_Method_Return_Type_Extension, Dynamic_Static_Method_Return_Type_Extension
{
    public function get_class(): string
    {
        return Test_Case::class;
    }
    public function is_method_supported(Method_Reflection $method_reflection): bool
    {
        return $method_reflection->get_name() === 'createMockForIntersectionOfInterfaces';
    }
    public function is_static_method_supported(Method_Reflection $method_reflection): bool
    {
        return $method_reflection->get_name() === 'createStubForIntersectionOfInterfaces';
    }
    public function get_type_from_static_method_call(Method_Reflection $method_reflection, Static_Call $method_call, Scope $scope): ?Type
    {
        return $this->get_type_from_call($method_reflection, $method_call->get_args(), $scope);
    }
    public function get_type_from_method_call(Method_Reflection $method_reflection, Method_Call $method_call, Scope $scope): ?Type
    {
        return $this->get_type_from_call($method_reflection, $method_call->get_args(), $scope);
    }
    /**
     * @param array<Arg> $args
     */
    private function get_type_from_call(Method_Reflection $method_reflection, array $args, Scope $scope): ?Type
    {
        if (!isset($args[0])) {
            return null;
        }
        $interfaces = $scope->get_type($args[0]->value);
        $constant_arrays = $interfaces->get_constant_arrays();
        if (count($constant_arrays) !== 1) {
            return null;
        }
        $constant_array = $constant_arrays[0];
        if (count($constant_array->get_optional_keys()) > 0) {
            return null;
        }
        $result = [];
        if ($method_reflection->get_name() === 'createMockForIntersectionOfInterfaces') {
            $result[] = new Object_Type(Mock_Object::class);
        } else {
            $result[] = new Object_Type(Stub::class);
        }
        foreach ($constant_array->get_value_types() as $value_type) {
            if (!$value_type->is_class_string()->yes()) {
                return null;
            }
            $values = $value_type->get_constant_scalar_values();
            if (count($values) !== 1) {
                return null;
            }
            $result[] = new Object_Type((string) $values[0]);
        }
        return Type_Combinator::intersect(...$result);
    }
}