<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Php_Unit;

use function array_filter;
use function count;
use function implode;
use function in_array;
use Php_Parser\Node;
use Php_Parser\Node\Expr\Method_Call;
use Php_Stan\Analyser\Scope;
use Php_Stan\Rules\Identifier_Rule_Error;
use Php_Stan\Rules\Rule;
use Php_Stan\Rules\Rule_Error_Builder;
use Php_Stan\Type\Type;
use Php_Unit\Framework\Mock_Object\Mock_Object;
use Php_Unit\Framework\Mock_Object\Stub;
use function sprintf;
/**
 * @implements Rule<MethodCall>
 */
class Mock_Method_Call_Rule implements Rule
{
    public function get_node_type(): string
    {
        return Node\Expr\Method_Call::class;
    }
    public function process_node(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Node\Identifier || $node->name->name !== 'method') {
            return [];
        }
        if (count($node->get_args()) < 1) {
            return [];
        }
        $arg_type = $scope->get_type($node->get_args()[0]->value);
        if (count($arg_type->get_constant_strings()) === 0) {
            return [];
        }
        $errors = [];
        foreach ($arg_type->get_constant_strings() as $constant_string) {
            $method = $constant_string->get_value();
            $type = $scope->get_type($node->var);
            $error = $this->check_call_on_type($scope, $type, $method);
            if ($error !== null) {
                $errors[] = $error;
                continue;
            }
            if (!$node->var instanceof Method_Call) {
                continue;
            }
            if (!$node->var->name instanceof Node\Identifier) {
                continue;
            }
            if ($node->var->name->to_lower_string() !== 'expects') {
                continue;
            }
            $var_type = $scope->get_type($node->var->var);
            $error = $this->check_call_on_type($scope, $var_type, $method);
            if ($error === null) {
                continue;
            }
            $errors[] = $error;
        }
        return $errors;
    }
    private function check_call_on_type(Scope $scope, Type $type, string $method): ?Identifier_Rule_Error
    {
        $method_reflection = $scope->get_method_reflection($type, $method);
        if ($method_reflection !== null) {
            return null;
        }
        if (in_array(Mock_Object::class, $type->get_object_class_names(), true) || in_array(Stub::class, $type->get_object_class_names(), true)) {
            $mock_classes = array_filter($type->get_object_class_names(), static fn(string $class): bool => $class !== Mock_Object::class && $class !== Stub::class);
            if (count($mock_classes) === 0) {
                return null;
            }
            return Rule_Error_Builder::message(sprintf('Trying to mock an undefined method %s() on class %s.', $method, implode('&', $mock_classes)))->identifier('phpunit.mockMethod')->build();
        }
        return null;
    }
}