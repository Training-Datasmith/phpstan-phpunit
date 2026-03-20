<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Php_Unit;

use function in_array;
use Php_Parser\Node;
use Php_Stan\Analyser\Scope;
use Php_Stan\Node\In_Class_Method_Node;
use Php_Stan\Rules\Rule;
use Php_Stan\Rules\Rule_Error_Builder;
use Php_Unit\Framework\Test_Case;
use function sprintf;
use function strtolower;
/**
 * @implements Rule<InClassMethodNode>
 */
class Should_Call_Parent_Methods_Rule implements Rule
{
    public function get_node_type(): string
    {
        return In_Class_Method_Node::class;
    }
    public function process_node(Node $node, Scope $scope): array
    {
        $method_name = $node->get_original_node()->name->name;
        if (!in_array(strtolower($method_name), ['setup', 'teardown'], true)) {
            return [];
        }
        if ($scope->get_class_reflection() === null) {
            return [];
        }
        if (!$scope->get_class_reflection()->is(Test_Case::class)) {
            return [];
        }
        $parent_class = $scope->get_class_reflection()->get_parent_class();
        if ($parent_class === null) {
            return [];
        }
        if (!$parent_class->has_native_method($method_name)) {
            return [];
        }
        $parent_method = $parent_class->get_native_method($method_name);
        if ($parent_method->get_declaring_class()->get_name() === Test_Case::class) {
            return [];
        }
        $has_parent_call = $this->has_parent_class_call($node->get_original_node()->get_stmts(), strtolower($method_name));
        if (!$has_parent_call) {
            return [Rule_Error_Builder::message(sprintf('Missing call to parent::%s() method.', $method_name))->identifier('phpunit.callParent')->build()];
        }
        return [];
    }
    /**
     * @param Node\Stmt[]|null $stmts
     *
     */
    private function has_parent_class_call(?array $stmts, string $method_name): bool
    {
        if ($stmts === null) {
            return false;
        }
        foreach ($stmts as $stmt) {
            if (!$stmt instanceof Node\Stmt\Expression) {
                continue;
            }
            if (!$stmt->expr instanceof Node\Expr\Static_Call) {
                continue;
            }
            if (!$stmt->expr->class instanceof Node\Name) {
                continue;
            }
            $class = (string) $stmt->expr->class;
            if (strtolower($class) !== 'parent') {
                continue;
            }
            if (!$stmt->expr->name instanceof Node\Identifier) {
                continue;
            }
            if ($stmt->expr->name->to_lower_string() === $method_name) {
                return true;
            }
        }
        return false;
    }
}