<?php

declare (strict_types=1);
namespace Php_Stan\Type\Php_Unit\Assert;

use Php_Parser\Node\Expr\Func_Call;
use Php_Stan\Analyser\Scope;
use Php_Stan\Analyser\Specified_Types;
use Php_Stan\Analyser\Type_Specifier;
use Php_Stan\Analyser\Type_Specifier_Aware_Extension;
use Php_Stan\Analyser\Type_Specifier_Context;
use Php_Stan\Reflection\Function_Reflection;
use Php_Stan\Type\Function_Type_Specifying_Extension;
use function strlen;
use function strpos;
use function substr;
class Assert_Function_Type_Specifying_Extension implements Function_Type_Specifying_Extension, Type_Specifier_Aware_Extension
{
    private Type_Specifier $type_specifier;
    public function set_type_specifier(Type_Specifier $type_specifier): void
    {
        $this->type_specifier = $type_specifier;
    }
    public function is_function_supported(Function_Reflection $function_reflection, Func_Call $node, Type_Specifier_Context $context): bool
    {
        return Assert_Type_Specifying_Extension_Helper::is_supported($this->trim_name($function_reflection->get_name()), $node->get_args());
    }
    public function specify_types(Function_Reflection $function_reflection, Func_Call $node, Scope $scope, Type_Specifier_Context $context): Specified_Types
    {
        return Assert_Type_Specifying_Extension_Helper::specify_types($this->type_specifier, $scope, $this->trim_name($function_reflection->get_name()), $node->get_args());
    }
    private function trim_name(string $function_name): string
    {
        $prefix = 'PHPUnit\Framework\\';
        if (strpos($function_name, $prefix) === 0) {
            return substr($function_name, strlen($prefix));
        }
        return $function_name;
    }
}