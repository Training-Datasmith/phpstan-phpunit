<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Php_Unit;

use function count;
use const COUNT_NORMAL;
use Countable;
use Php_Parser\Node;
use Php_Parser\Node\Expr\Call_Like;
use Php_Stan\Analyser\Scope;
use Php_Stan\Rules\Rule;
use Php_Stan\Rules\Rule_Error_Builder;
use Php_Stan\Trinary_Logic;
use Php_Stan\Type\Constant\Constant_Integer_Type;
use Php_Stan\Type\Object_Type;
use Php_Stan\Type\Type;
/**
 * @implements Rule<CallLike>
 */
class Assert_Same_With_Count_Rule implements Rule
{
    public function get_node_type(): string
    {
        return Call_Like::class;
    }
    public function process_node(Node $node, Scope $scope): array
    {
        if (!$node instanceof Node\Expr\Method_Call && !$node instanceof Node\Expr\Static_Call) {
            return [];
        }
        if (count($node->get_args()) < 2) {
            return [];
        }
        if ($node->is_first_class_callable()) {
            return [];
        }
        if (!$node->name instanceof Node\Identifier || $node->name->to_lower_string() !== 'assertsame') {
            return [];
        }
        if (!Assert_Rule_Helper::is_method_or_static_call_on_assert($node, $scope)) {
            return [];
        }
        $right = $node->get_args()[1]->value;
        if (self::is_count_function_call($right, $scope)) {
            return [Rule_Error_Builder::message('You should use assertCount($expectedCount, $variable) instead of assertSame($expectedCount, count($variable)).')->identifier('phpunit.assertCount')->build()];
        }
        if (self::is_countable_method_call($right, $scope)) {
            return [Rule_Error_Builder::message('You should use assertCount($expectedCount, $variable) instead of assertSame($expectedCount, $variable->count()).')->identifier('phpunit.assertCount')->build()];
        }
        return [];
    }
    /**
     * @phpstan-assert-if-true Node\Expr\FuncCall $expr
     */
    private static function is_count_function_call(Node\Expr $expr, Scope $scope): bool
    {
        return $expr instanceof Node\Expr\Func_Call && $expr->name instanceof Node\Name && $expr->name->to_lower_string() === 'count' && count($expr->get_args()) >= 1 && self::is_normal_count($expr, $scope->get_type($expr->get_args()[0]->value), $scope)->yes();
    }
    /**
     * @phpstan-assert-if-true Node\Expr\MethodCall $expr
     */
    private static function is_countable_method_call(Node\Expr $expr, Scope $scope): bool
    {
        if ($expr instanceof Node\Expr\Method_Call && $expr->name instanceof Node\Identifier && $expr->name->to_lower_string() === 'count' && count($expr->get_args()) === 0) {
            $type = $scope->get_type($expr->var);
            if ((new Object_Type(Countable::class))->is_super_type_of($type)->yes()) {
                return true;
            }
        }
        return false;
    }
    private static function is_normal_count(Node\Expr\Func_Call $count_func_call, Type $counted_type, Scope $scope): Trinary_Logic
    {
        if (count($count_func_call->get_args()) === 1) {
            return Trinary_Logic::create_yes();
        }
        $mode = $scope->get_type($count_func_call->get_args()[1]->value);
        return (new Constant_Integer_Type(COUNT_NORMAL))->is_super_type_of($mode)->result->or($counted_type->get_iterable_value_type()->is_array()->negate());
    }
}