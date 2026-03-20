<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Php_Unit;

use function array_merge;
use Php_Parser\Node;
use Php_Stan\Analyser\Scope;
use Php_Stan\Rules\Rule;
use Php_Unit\Framework\Test_Case;
/**
 * @implements Rule<Node\Stmt\ClassMethod>
 */
class Data_Provider_Declaration_Rule implements Rule
{
    /**
     * Data provider helper.
     *
     */
    private Data_Provider_Helper $data_provider_helper;
    /**
     * When set to true, it reports data provider method with incorrect name case.
     *
     */
    private bool $check_function_name_case;
    /**
     * When phpstan-deprecation-rules is installed, it reports deprecated usages.
     *
     */
    private bool $deprecation_rules_installed;
    public function __construct(Data_Provider_Helper $data_provider_helper, bool $check_function_name_case, bool $deprecation_rules_installed)
    {
        $this->data_provider_helper = $data_provider_helper;
        $this->check_function_name_case = $check_function_name_case;
        $this->deprecation_rules_installed = $deprecation_rules_installed;
    }
    public function get_node_type(): string
    {
        return Node\Stmt\Class_Method::class;
    }
    public function process_node(Node $node, Scope $scope): array
    {
        $class_reflection = $scope->get_class_reflection();
        if ($class_reflection === null || !$class_reflection->is(Test_Case::class)) {
            return [];
        }
        $errors = [];
        foreach ($this->data_provider_helper->get_data_provider_methods($scope, $node, $class_reflection) as $data_provider_value => [$data_provider_class_reflection, $data_provider_method_name, $line_number]) {
            $errors = array_merge($errors, $this->data_provider_helper->process_data_provider($data_provider_value, $data_provider_class_reflection, $data_provider_method_name, $line_number, $this->check_function_name_case, $this->deprecation_rules_installed));
        }
        return $errors;
    }
}