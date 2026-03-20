<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Php_Unit;

use function count;
use function in_array;
use Php_Parser\Node;
use Php_Parser\Node\Expr\Call_Like;
use Php_Stan\Analyser\Scope;
use Php_Stan\Rules\Rule;
use Php_Stan\Rules\Rule_Error_Builder;
use Php_Stan\Type\Generalize_Precision;
use Php_Stan\Type\Type_Combinator;
use function sprintf;
use function strtolower;
/**
 * @implements Rule<CallLike>
 */
class Assert_Equals_Is_Discouraged_Rule implements Rule
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
        if (!$node->name instanceof Node\Identifier || !in_array(strtolower($node->name->name), ['assertequals', 'assertnotequals'], true)) {
            return [];
        }
        if (!Assert_Rule_Helper::is_method_or_static_call_on_assert($node, $scope)) {
            return [];
        }
        $left_type = Type_Combinator::remove_null($scope->get_type($node->get_args()[0]->value));
        $right_type = Type_Combinator::remove_null($scope->get_type($node->get_args()[1]->value));
        if ($left_type->is_constant_scalar_value()->yes()) {
            $left_type = $left_type->generalize(Generalize_Precision::less_specific());
        }
        if ($right_type->is_constant_scalar_value()->yes()) {
            $right_type = $right_type->generalize(Generalize_Precision::less_specific());
        }
        if ($left_type->is_scalar()->yes() && $right_type->is_scalar()->yes() && $left_type->is_super_type_of($right_type)->yes() && $right_type->is_super_type_of($left_type)->yes()) {
            $correct_name = strtolower($node->name->name) === 'assertnotequals' ? 'assertNotSame' : 'assertSame';
            return [Rule_Error_Builder::message(sprintf('You should use %s() instead of %s(), because both values are scalars of the same type', $correct_name, $node->name->name))->identifier('phpunit.assertEquals')->fix_node($node, static function (Call_Like $node) use ($correct_name) {
                $node->name = new Node\Identifier($correct_name);
                return $node;
            })->build()];
        }
        return [];
    }
}