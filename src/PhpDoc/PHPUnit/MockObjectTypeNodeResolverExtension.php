<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc\Php_Unit;

use function array_key_exists;
use function count;
use Php_Stan\Analyser\Name_Scope;
use Php_Stan\Php_Doc\Type_Node_Resolver;
use Php_Stan\Php_Doc\Type_Node_Resolver_Aware_Extension;
use Php_Stan\Php_Doc\Type_Node_Resolver_Extension;
use Php_Stan\Php_Doc_Parser\Ast\Type\Type_Node;
use Php_Stan\Php_Doc_Parser\Ast\Type\Union_Type_Node;
use Php_Stan\Type\Never_Type;
use Php_Stan\Type\Type;
use Php_Stan\Type\Type_Combinator;
class Mock_Object_Type_Node_Resolver_Extension implements Type_Node_Resolver_Extension, Type_Node_Resolver_Aware_Extension
{
    private Type_Node_Resolver $type_node_resolver;
    public function set_type_node_resolver(Type_Node_Resolver $type_node_resolver): void
    {
        $this->type_node_resolver = $type_node_resolver;
    }
    public function get_cache_key(): string
    {
        return 'phpunit-v1';
    }
    public function resolve(Type_Node $type_node, Name_Scope $name_scope): ?Type
    {
        if (!$type_node instanceof Union_Type_Node) {
            return null;
        }
        static $mock_class_names = ['PHPUnit_Framework_MockObject_MockObject' => true, 'PHPUnit\Framework\MockObject\MockObject' => true, 'PHPUnit\Framework\MockObject\Stub' => true];
        $types = $this->type_node_resolver->resolve_multiple($type_node->types, $name_scope);
        foreach ($types as $type) {
            $class_names = $type->get_object_class_names();
            if (count($class_names) !== 1) {
                continue;
            }
            if (array_key_exists($class_names[0], $mock_class_names)) {
                $result_type = Type_Combinator::intersect(...$types);
                if ($result_type instanceof Never_Type) {
                    continue;
                }
                return $result_type;
            }
        }
        return null;
    }
}