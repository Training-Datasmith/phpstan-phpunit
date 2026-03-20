<?php

declare (strict_types=1);
namespace Php_Stan\Type\Php_Unit\Assert;

use Php_Parser\Node\Expr\Method_Call;
use Php_Stan\Analyser\Scope;
use Php_Stan\Analyser\Specified_Types;
use Php_Stan\Analyser\Type_Specifier;
use Php_Stan\Analyser\Type_Specifier_Aware_Extension;
use Php_Stan\Analyser\Type_Specifier_Context;
use Php_Stan\Reflection\Method_Reflection;
use Php_Stan\Type\Method_Type_Specifying_Extension;
class Assert_Method_Type_Specifying_Extension implements Method_Type_Specifying_Extension, Type_Specifier_Aware_Extension
{
    private Type_Specifier $type_specifier;
    public function set_type_specifier(Type_Specifier $type_specifier): void
    {
        $this->type_specifier = $type_specifier;
    }
    public function get_class(): string
    {
        return 'PHPUnit\Framework\Assert';
    }
    public function is_method_supported(Method_Reflection $method_reflection, Method_Call $node, Type_Specifier_Context $context): bool
    {
        return Assert_Type_Specifying_Extension_Helper::is_supported($method_reflection->get_name(), $node->get_args());
    }
    public function specify_types(Method_Reflection $function_reflection, Method_Call $node, Scope $scope, Type_Specifier_Context $context): Specified_Types
    {
        return Assert_Type_Specifying_Extension_Helper::specify_types($this->type_specifier, $scope, $function_reflection->get_name(), $node->get_args());
    }
}