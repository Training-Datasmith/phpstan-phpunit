<?php

declare (strict_types=1);
namespace Php_Stan\Type\Php_Unit;

use function is_string;
use Php_Parser\Node;
use Php_Stan\Analyser\Error;
use Php_Stan\Analyser\Ignore_Error_Extension;
use Php_Stan\Analyser\Scope;
use Php_Unit\Framework\Test_Case;
use function str_starts_with;
final class Dynamic_Call_To_Assertion_Ignore_Extension implements Ignore_Error_Extension
{
    public function should_ignore(Error $error, Node $node, Scope $scope): bool
    {
        if (!$node instanceof Node\Expr\Method_Call) {
            return false;
        }
        if (!$node->var instanceof Node\Expr\Variable) {
            return false;
        }
        if (!is_string($node->var->name) || $node->var->name !== 'this') {
            return false;
        }
        if ($error->get_identifier() !== 'staticMethod.dynamicCall') {
            return false;
        }
        if (!$node->name instanceof Node\Identifier || !str_starts_with($node->name->name, 'assert')) {
            return false;
        }
        if (!$scope->is_in_class()) {
            return false;
        }
        $class_reflection = $scope->get_class_reflection();
        return $class_reflection->is(Test_Case::class);
    }
}