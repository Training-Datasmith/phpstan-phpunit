<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Php_Unit;

use function array_slice;
use function count;
use function max;
use const PHP_INT_MAX;
use Php_Parser\Node;
use Php_Stan\Analyser\Scope;
use Php_Stan\Node\Expr\Type_Expr;
use Php_Stan\Rules\Rule;
use Php_Stan\Type\Object_Type;
use Php_Stan\Type\Type;
/**
 * @implements Rule<Node>
 */
class Data_Provider_Data_Rule implements Rule
{
    private Test_Methods_Helper $test_methods_helper;
    private Data_Provider_Helper $data_provider_helper;
    private Php_Unit_Version $php_unit_version;
    public function __construct(Test_Methods_Helper $test_methods_helper, Data_Provider_Helper $data_provider_helper, Php_Unit_Version $php_unit_version)
    {
        $this->test_methods_helper = $test_methods_helper;
        $this->data_provider_helper = $data_provider_helper;
        $this->php_unit_version = $php_unit_version;
    }
    public function get_node_type(): string
    {
        return Node::class;
    }
    public function process_node(Node $node, Scope $scope): array
    {
        if (!$node instanceof Node\Stmt\Return_ && !$node instanceof Node\Expr\Yield_ && !$node instanceof Node\Expr\Yield_From) {
            return [];
        }
        if ($scope->get_function() === null) {
            return [];
        }
        if ($scope->is_in_anonymous_function()) {
            return [];
        }
        $class_reflection = $scope->get_class_reflection();
        if ($class_reflection === null) {
            return [];
        }
        $tests_with_provider = [];
        $method = $scope->get_function();
        $test_methods = $this->test_methods_helper->get_test_methods($class_reflection, $scope);
        foreach ($test_methods as $test_method) {
            foreach ($this->data_provider_helper->get_data_provider_methods($scope, $test_method, $class_reflection) as [, $provider_method_name]) {
                if ($provider_method_name === $method->get_name()) {
                    $tests_with_provider[] = $test_method;
                    continue 2;
                }
            }
        }
        if (count($tests_with_provider) === 0) {
            return [];
        }
        $arrays_types = $this->build_array_types_from_node($node, $scope);
        if ($arrays_types === []) {
            return [];
        }
        $max_number_of_parameters = null;
        foreach ($tests_with_provider as $test_method) {
            $num = $test_method->get_number_of_parameters();
            if ($test_method->is_variadic()) {
                $num = PHP_INT_MAX;
            }
            if ($max_number_of_parameters === null) {
                $max_number_of_parameters = $num;
                continue;
            }
            $max_number_of_parameters = max($max_number_of_parameters, $num);
            if ($num === PHP_INT_MAX) {
                break;
            }
        }
        foreach ($tests_with_provider as $test_method) {
            $number_of_parameters = $test_method->get_number_of_parameters();
            foreach ($arrays_types as [$start_line, $arrays_type]) {
                $args = $this->array_items_to_args($arrays_type, $number_of_parameters);
                if ($args === null) {
                    continue;
                }
                if (!$test_method->is_variadic() && $number_of_parameters !== $max_number_of_parameters) {
                    $args = array_slice($args, 0, $number_of_parameters);
                }
                $scope->invoke_node_callback(new Node\Expr\Method_Call(new Type_Expr(new Object_Type($class_reflection->get_name())), $test_method->get_name(), $args, ['startLine' => $start_line]));
            }
        }
        return [];
    }
    /**
     * @return array<Node\Arg>
     */
    private function array_items_to_args(Type $array, int $number_of_parameters): ?array
    {
        $args = [];
        $const_arrays = $array->get_constant_arrays();
        if ($const_arrays !== [] && count($const_arrays) === 1) {
            $key_types = $const_arrays[0]->get_key_types();
            $value_types = $const_arrays[0]->get_value_types();
        } elseif ($array->is_array()->yes()) {
            $key_types = [];
            $value_types = [];
            for ($i = 0; $i < $number_of_parameters; ++$i) {
                $key_types[$i] = $array->get_iterable_key_type();
                $value_types[$i] = $array->get_iterable_value_type();
            }
        } else {
            return null;
        }
        foreach ($value_types as $i => $value_type) {
            $key = $key_types[$i]->get_constant_strings();
            if (count($key) > 1) {
                return null;
            }
            if (count($key) === 0 || !$this->php_unit_version->supports_named_arguments_in_data_provider()->yes()) {
                $arg = new Node\Arg(new Type_Expr($value_type));
                $args[] = $arg;
                continue;
            }
            $arg = new Node\Arg(new Type_Expr($value_type), false, false, [], new Node\Identifier($key[0]->get_value()));
            $args[] = $arg;
        }
        return $args;
    }
    /**
     * @param Node\Stmt\Return_|Node\Expr\Yield_|Node\Expr\YieldFrom $node
     *
     * @return list<list{int, Type}>
     */
    private function build_array_types_from_node(Node $node, Scope $scope): array
    {
        $arrays_types = [];
        // special case for providers only containing static data, so we get more precise error lines
        if ($node instanceof Node\Stmt\Return_ && $node->expr instanceof Node\Expr\Array_ || $node instanceof Node\Expr\Yield_From && $node->expr instanceof Node\Expr\Array_) {
            foreach ($node->expr->items as $item) {
                if (!$item->value instanceof Node\Expr\Array_) {
                    $arrays_types = [];
                    break;
                }
                $const_arrays = $scope->get_type($item->value)->get_constant_arrays();
                if ($const_arrays === []) {
                    $arrays_types = [];
                    break;
                }
                foreach ($const_arrays as $const_array) {
                    $arrays_types[] = [$item->value->get_start_line(), $const_array];
                }
            }
            if ($arrays_types !== []) {
                return $arrays_types;
            }
        }
        // general case with less precise error message lines
        if ($node instanceof Node\Stmt\Return_ || $node instanceof Node\Expr\Yield_From) {
            if ($node->expr === null) {
                return [];
            }
            $expr_type = $scope->get_type($node->expr);
            $expr_const_arrays = $expr_type->get_constant_arrays();
            foreach ($expr_const_arrays as $const_array) {
                foreach ($const_array->get_value_types() as $value_type) {
                    foreach ($value_type->get_constant_arrays() as $const_value_array) {
                        $arrays_types[] = [$node->get_start_line(), $const_value_array];
                    }
                }
            }
            if ($arrays_types === []) {
                foreach ($expr_type->get_iterable_value_type()->get_arrays() as $array_type) {
                    $arrays_types[] = [$node->get_start_line(), $array_type];
                }
            }
        } elseif ($node instanceof Node\Expr\Yield_) {
            if ($node->value === null) {
                return [];
            }
            $expr_type = $scope->get_type($node->value);
            foreach ($expr_type->get_constant_arrays() as $const_value_array) {
                $arrays_types[] = [$node->get_start_line(), $const_value_array];
            }
        }
        return $arrays_types;
    }
}