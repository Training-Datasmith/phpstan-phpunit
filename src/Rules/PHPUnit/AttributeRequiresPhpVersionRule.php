<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Php_Unit;

use function count;
use function is_numeric;
use Php_Parser\Node;
use Php_Stan\Analyser\Scope;
use Php_Stan\Node\In_Class_Method_Node;
use Php_Stan\Rules\Rule;
use Php_Stan\Rules\Rule_Error_Builder;
use Php_Unit\Framework\Test_Case;
use function sprintf;
/**
 * @implements Rule<InClassMethodNode>
 */
class Attribute_Requires_Php_Version_Rule implements Rule
{
    private Php_Unit_Version $php_unit_version;
    private Test_Methods_Helper $test_methods_helper;
    /**
     * When phpstan-deprecation-rules is installed, it reports deprecated usages.
     */
    private bool $deprecation_rules_installed;
    public function __construct(Php_Unit_Version $php_unit_version, Test_Methods_Helper $test_methods_helper, bool $deprecation_rules_installed)
    {
        $this->php_unit_version = $php_unit_version;
        $this->test_methods_helper = $test_methods_helper;
        $this->deprecation_rules_installed = $deprecation_rules_installed;
    }
    public function get_node_type(): string
    {
        return In_Class_Method_Node::class;
    }
    public function process_node(Node $node, Scope $scope): array
    {
        $class_reflection = $scope->get_class_reflection();
        if ($class_reflection === null || $class_reflection->is(Test_Case::class) === false) {
            return [];
        }
        $reflection_method = $this->test_methods_helper->get_test_method_reflection($class_reflection, $node->get_method_reflection(), $scope);
        if ($reflection_method === null) {
            return [];
        }
        $errors = [];
        foreach ($reflection_method->get_attributes_by_name('PHPUnit\Framework\Attributes\RequiresPhp') as $attr) {
            $args = $attr->get_arguments();
            if (count($args) !== 1) {
                continue;
            }
            if (!is_numeric($args[0])) {
                continue;
            }
            if ($this->php_unit_version->requires_phpversion_attribute_with_operator()->yes()) {
                $errors[] = Rule_Error_Builder::message(sprintf('Version requirement is missing operator.'))->identifier('phpunit.attributeRequiresPhpVersion')->build();
            } elseif ($this->deprecation_rules_installed && $this->php_unit_version->deprecates_phpversion_attribute_without_operator()->yes()) {
                $errors[] = Rule_Error_Builder::message(sprintf('Version requirement without operator is deprecated.'))->identifier('phpunit.attributeRequiresPhpVersion')->build();
            }
        }
        return $errors;
    }
}