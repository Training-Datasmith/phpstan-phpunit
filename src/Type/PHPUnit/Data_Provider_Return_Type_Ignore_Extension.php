<?php

declare (strict_types=1);
namespace Php_Stan\Type\Php_Unit;

use function in_array;
use Php_Parser\Node;
use Php_Stan\Analyser\Error;
use Php_Stan\Analyser\Ignore_Error_Extension;
use Php_Stan\Analyser\Scope;
use Php_Stan\Rules\Php_Unit\Data_Provider_Helper;
use Php_Stan\Rules\Php_Unit\Test_Methods_Helper;
final class Data_Provider_Return_Type_Ignore_Extension implements Ignore_Error_Extension
{
    private Test_Methods_Helper $test_methods_helper;
    private Data_Provider_Helper $data_provider_helper;
    public function __construct(Test_Methods_Helper $test_methods_helper, Data_Provider_Helper $data_provider_helper)
    {
        $this->test_methods_helper = $test_methods_helper;
        $this->data_provider_helper = $data_provider_helper;
    }
    public function should_ignore(Error $error, Node $node, Scope $scope): bool
    {
        if (!in_array($error->get_identifier(), ['missingType.iterableValue', 'missingType.generics'], true)) {
            return false;
        }
        if (!$scope->is_in_class()) {
            return false;
        }
        $class_reflection = $scope->get_class_reflection();
        $method_reflection = $scope->get_function();
        if ($method_reflection === null) {
            return false;
        }
        $test_methods = $this->test_methods_helper->get_test_methods($class_reflection, $scope);
        foreach ($test_methods as $test_method) {
            foreach ($this->data_provider_helper->get_data_provider_methods($scope, $test_method, $class_reflection) as [, $provider_method_name]) {
                if ($provider_method_name === $method_reflection->get_name()) {
                    return true;
                }
            }
        }
        return false;
    }
}