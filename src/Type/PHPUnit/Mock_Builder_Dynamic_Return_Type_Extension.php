<?php

declare (strict_types=1);
namespace Php_Stan\Type\Php_Unit;

use function in_array;
use Php_Parser\Node\Expr\Method_Call;
use Php_Stan\Analyser\Scope;
use Php_Stan\Reflection\Method_Reflection;
use Php_Stan\Type\Dynamic_Method_Return_Type_Extension;
use Php_Stan\Type\Type;
use Php_Unit\Framework\Mock_Object\Mock_Builder;
class Mock_Builder_Dynamic_Return_Type_Extension implements Dynamic_Method_Return_Type_Extension
{
    public function get_class(): string
    {
        return Mock_Builder::class;
    }
    public function is_method_supported(Method_Reflection $method_reflection): bool
    {
        return !in_array($method_reflection->get_name(), ['getMock', 'getMockForAbstractClass', 'getMockForTrait'], true);
    }
    public function get_type_from_method_call(Method_Reflection $method_reflection, Method_Call $method_call, Scope $scope): Type
    {
        return $scope->get_type($method_call->var);
    }
}