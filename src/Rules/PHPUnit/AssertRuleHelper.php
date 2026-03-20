<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Php_Unit;

use function in_array;
use Php_Parser\Node;
use Php_Stan\Analyser\Scope;
use Php_Stan\Type\Object_Type;
use function strtolower;
class Assert_Rule_Helper
{
    public static function is_method_or_static_call_on_assert(Node $node, Scope $scope): bool
    {
        if ($node instanceof Node\Expr\Method_Call) {
            $called_on_type = $scope->get_type($node->var);
        } elseif ($node instanceof Node\Expr\Static_Call) {
            if ($node->class instanceof Node\Name) {
                $class = (string) $node->class;
                if ($scope->is_in_class() && in_array(strtolower($class), ['self', 'static', 'parent'], true)) {
                    $called_on_type = new Object_Type($scope->get_class_reflection()->get_name());
                } else {
                    $called_on_type = new Object_Type($class);
                }
            } else {
                $called_on_type = $scope->get_type($node->class);
            }
        } else {
            return false;
        }
        $test_case_type = new Object_Type('PHPUnit\Framework\Assert');
        return $test_case_type->is_super_type_of($called_on_type)->yes();
    }
}