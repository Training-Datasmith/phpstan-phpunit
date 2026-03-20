<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Php_Unit;

use function count;
use Php_Parser\Node;
use Php_Parser\Node\Expr\Call_Like;
use Php_Parser\Node\Expr\Const_Fetch;
use Php_Stan\Analyser\Scope;
use Php_Stan\Rules\Rule;
use Php_Stan\Rules\Rule_Error_Builder;
/**
 * @implements Rule<CallLike>
 */
class Assert_Same_Boolean_Expected_Rule implements Rule
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
        $expected_argument_value = $node->get_args()[0]->value;
        if (!$expected_argument_value instanceof Const_Fetch) {
            return [];
        }
        if (!Assert_Rule_Helper::is_method_or_static_call_on_assert($node, $scope)) {
            return [];
        }
        if ($expected_argument_value->name->to_lower_string() === 'true') {
            return [Rule_Error_Builder::message('You should use assertTrue() instead of assertSame() when expecting "true"')->identifier('phpunit.assertTrue')->fix_node($node, static function (Call_Like $node) {
                $node->name = new Node\Identifier('assertTrue');
                $node->args = self::rewrite_args($node->args);
                return $node;
            })->build()];
        }
        if ($expected_argument_value->name->to_lower_string() === 'false') {
            return [Rule_Error_Builder::message('You should use assertFalse() instead of assertSame() when expecting "false"')->identifier('phpunit.assertFalse')->fix_node($node, static function (Call_Like $node) {
                $node->name = new Node\Identifier('assertFalse');
                $node->args = self::rewrite_args($node->args);
                return $node;
            })->build()];
        }
        return [];
    }
    /**
     * @param array<Node\Arg|Node\VariadicPlaceholder> $args
     * @return list<Node\Arg|Node\VariadicPlaceholder>
     */
    private static function rewrite_args(array $args): array
    {
        $new_args = [];
        for ($i = 1; $i < count($args); $i++) {
            $new_args[] = $args[$i];
        }
        return $new_args;
    }
}