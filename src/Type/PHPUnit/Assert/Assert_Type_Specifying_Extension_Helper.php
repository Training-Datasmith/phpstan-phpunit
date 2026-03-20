<?php

declare (strict_types=1);
namespace Php_Stan\Type\Php_Unit\Assert;

use function array_key_exists;
use Closure;
use function count;
use Countable;
use Empty_Iterator;
use function in_array;
use Php_Parser\Node\Arg;
use Php_Parser\Node\Expr;
use Php_Parser\Node\Expr\Binary_Op\Identical;
use Php_Parser\Node\Expr\Boolean_Not;
use Php_Parser\Node\Expr\Const_Fetch;
use Php_Parser\Node\Expr\Func_Call;
use Php_Parser\Node\Expr\Instanceof_;
use Php_Parser\Node\Name;
use Php_Parser\Node\Param;
use Php_Parser\Node\Scalar\L_Number;
use Php_Parser\Node\Stmt;
use Php_Stan\Analyser\Scope;
use Php_Stan\Analyser\Specified_Types;
use Php_Stan\Analyser\Type_Specifier;
use Php_Stan\Analyser\Type_Specifier_Context;
use Reflection_Object;
use function strlen;
use function strpos;
use function substr;
class Assert_Type_Specifying_Extension_Helper
{
    /** @var Closure[] */
    private static ?array $resolvers = null;
    /**
     * Those can specify types correctly, but would produce always-true issue
     * @var string[]
     */
    private static array $resolvers_causing_always_true = ['ContainsOnlyInstancesOf', 'ContainsEquals', 'Contains'];
    /**
     * @param Arg[] $args
     */
    public static function is_supported(string $name, array $args): bool
    {
        $trimmed_name = self::trim_name($name);
        $resolvers = self::get_expression_resolvers();
        if (!array_key_exists($trimmed_name, $resolvers)) {
            return false;
        }
        $resolver = $resolvers[$trimmed_name];
        $resolver_reflection = new Reflection_Object($resolver);
        return count($args) >= count($resolver_reflection->get_method('__invoke')->get_parameters()) - 1;
    }
    private static function trim_name(string $name): string
    {
        if (strpos($name, 'assert') !== 0) {
            return $name;
        }
        $name = substr($name, strlen('assert'));
        if (strpos($name, 'Not') === 0) {
            return substr($name, 3);
        }
        if (strpos($name, 'IsNot') === 0) {
            return 'Is' . substr($name, 5);
        }
        return $name;
    }
    /**
     * @param Arg[] $args $args
     */
    public static function specify_types(Type_Specifier $type_specifier, Scope $scope, string $name, array $args): Specified_Types
    {
        $expression = self::create_expression($scope, $name, $args);
        if ($expression === null) {
            return new Specified_Types([], []);
        }
        $bypass_always_true_issue = in_array(self::trim_name($name), self::$resolvers_causing_always_true, true);
        return $type_specifier->specify_types_in_condition($scope, $expression, Type_Specifier_Context::create_truthy())->set_root_expr($bypass_always_true_issue ? new Expr\Binary_Op\Boolean_And($expression, new Expr\Variable('nonsense')) : $expression);
    }
    /**
     * @param Arg[] $args
     */
    private static function create_expression(Scope $scope, string $name, array $args): ?Expr
    {
        $trimmed_name = self::trim_name($name);
        $resolvers = self::get_expression_resolvers();
        $resolver = $resolvers[$trimmed_name];
        $expression = $resolver($scope, ...$args);
        if ($expression === null) {
            return null;
        }
        if (strpos($name, 'Not') !== false) {
            return new Boolean_Not($expression);
        }
        return $expression;
    }
    /**
     * @return Closure[]
     */
    private static function get_expression_resolvers(): array
    {
        if (self::$resolvers === null) {
            self::$resolvers = ['Count' => static fn(Scope $scope, Arg $expected, Arg $actual): Identical => new Identical($expected->value, new Func_Call(new Name('count'), [$actual])), 'NotCount' => static fn(Scope $scope, Arg $expected, Arg $actual): Boolean_Not => new Boolean_Not(new Identical($expected->value, new Func_Call(new Name('count'), [$actual]))), 'InstanceOf' => static fn(Scope $scope, Arg $class, Arg $object): Instanceof_ => new Instanceof_($object->value, $class->value), 'Same' => static fn(Scope $scope, Arg $expected, Arg $actual): Identical => new Identical($expected->value, $actual->value), 'True' => static fn(Scope $scope, Arg $actual): Identical => new Identical($actual->value, new Const_Fetch(new Name('true'))), 'False' => static fn(Scope $scope, Arg $actual): Identical => new Identical($actual->value, new Const_Fetch(new Name('false'))), 'Null' => static fn(Scope $scope, Arg $actual): Identical => new Identical($actual->value, new Const_Fetch(new Name('null'))), 'Empty' => static fn(Scope $scope, Arg $actual): Expr\Binary_Op\Boolean_Or => new Expr\Binary_Op\Boolean_Or(new Instanceof_($actual->value, new Name(Empty_Iterator::class)), new Expr\Binary_Op\Boolean_Or(new Expr\Binary_Op\Boolean_And(new Instanceof_($actual->value, new Name(Countable::class)), new Identical(new Func_Call(new Name('count'), [new Arg($actual->value)]), new L_Number(0))), new Expr\Empty_($actual->value))), 'IsArray' => static fn(Scope $scope, Arg $actual): Func_Call => new Func_Call(new Name('is_array'), [$actual]), 'IsBool' => static fn(Scope $scope, Arg $actual): Func_Call => new Func_Call(new Name('is_bool'), [$actual]), 'IsCallable' => static fn(Scope $scope, Arg $actual): Func_Call => new Func_Call(new Name('is_callable'), [$actual]), 'IsFloat' => static fn(Scope $scope, Arg $actual): Func_Call => new Func_Call(new Name('is_float'), [$actual]), 'IsInt' => static fn(Scope $scope, Arg $actual): Func_Call => new Func_Call(new Name('is_int'), [$actual]), 'IsIterable' => static fn(Scope $scope, Arg $actual): Func_Call => new Func_Call(new Name('is_iterable'), [$actual]), 'IsNumeric' => static fn(Scope $scope, Arg $actual): Func_Call => new Func_Call(new Name('is_numeric'), [$actual]), 'IsObject' => static fn(Scope $scope, Arg $actual): Func_Call => new Func_Call(new Name('is_object'), [$actual]), 'IsResource' => static fn(Scope $scope, Arg $actual): Func_Call => new Func_Call(new Name('is_resource'), [$actual]), 'IsString' => static fn(Scope $scope, Arg $actual): Func_Call => new Func_Call(new Name('is_string'), [$actual]), 'IsScalar' => static fn(Scope $scope, Arg $actual): Func_Call => new Func_Call(new Name('is_scalar'), [$actual]), 'InternalType' => static function (Scope $scope, Arg $type, Arg $value): ?Func_Call {
                $type_names = $scope->get_type($type->value)->get_constant_strings();
                if (count($type_names) !== 1) {
                    return null;
                }
                switch ($type_names[0]->get_value()) {
                    case 'numeric':
                        $function_name = 'is_numeric';
                        break;
                    case 'integer':
                    case 'int':
                        $function_name = 'is_int';
                        break;
                    case 'double':
                    case 'float':
                    case 'real':
                        $function_name = 'is_float';
                        break;
                    case 'string':
                        $function_name = 'is_string';
                        break;
                    case 'boolean':
                    case 'bool':
                        $function_name = 'is_bool';
                        break;
                    case 'scalar':
                        $function_name = 'is_scalar';
                        break;
                    case 'null':
                        $function_name = 'is_null';
                        break;
                    case 'array':
                        $function_name = 'is_array';
                        break;
                    case 'object':
                        $function_name = 'is_object';
                        break;
                    case 'resource':
                        $function_name = 'is_resource';
                        break;
                    case 'callable':
                        $function_name = 'is_callable';
                        break;
                    default:
                        return null;
                }
                return new Func_Call(new Name($function_name), [$value]);
            }, 'ArrayHasKey' => static fn(Scope $scope, Arg $key, Arg $array): Expr => new Expr\Binary_Op\Boolean_Or(new Expr\Binary_Op\Boolean_And(new Expr\Instanceof_($array->value, new Name('ArrayAccess')), new Expr\Method_Call($array->value, 'offsetExists', [$key])), new Func_Call(new Name('array_key_exists'), [$key, $array])), 'ObjectHasAttribute' => static fn(Scope $scope, Arg $property, Arg $object): Func_Call => new Func_Call(new Name('property_exists'), [$object, $property]), 'ObjectHasProperty' => static fn(Scope $scope, Arg $property, Arg $object): Func_Call => new Func_Call(new Name('property_exists'), [$object, $property]), 'Contains' => static fn(Scope $scope, Arg $needle, Arg $haystack): Expr => new Expr\Binary_Op\Boolean_Or(new Expr\Instanceof_($haystack->value, new Name('Traversable')), new Func_Call(new Name('in_array'), [$needle, $haystack, new Arg(new Const_Fetch(new Name('true')))])), 'ContainsEquals' => static fn(Scope $scope, Arg $needle, Arg $haystack): Expr => new Expr\Binary_Op\Boolean_Or(new Expr\Instanceof_($haystack->value, new Name('Traversable')), new Expr\Binary_Op\Boolean_And(new Expr\Boolean_Not(new Expr\Empty_($haystack->value)), new Func_Call(new Name('in_array'), [$needle, $haystack, new Arg(new Const_Fetch(new Name('false')))]))), 'ContainsOnlyInstancesOf' => static fn(Scope $scope, Arg $class_name, Arg $haystack): Expr => new Expr\Binary_Op\Boolean_Or(new Expr\Instanceof_($haystack->value, new Name('Traversable')), new Identical($haystack->value, new Func_Call(new Name('array_filter'), [$haystack, new Arg(new Expr\Closure(['static' => true, 'params' => [new Param(new Expr\Variable('_'))], 'stmts' => [new Stmt\Return_(new Func_Call(new Name('is_a'), [new Arg(new Expr\Variable('_')), $class_name]))]]))])))];
        }
        return self::$resolvers;
    }
}