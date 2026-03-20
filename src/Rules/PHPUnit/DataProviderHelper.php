<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Php_Unit;

use function array_merge;
use function count;
use function explode;
use Php_Parser\Comment\Doc;
use Php_Parser\Modifiers;
use Php_Parser\Node\Attribute;
use Php_Parser\Node\Expr\Class_Const_Fetch;
use Php_Parser\Node\Name;
use Php_Parser\Node\Scalar\String_;
use Php_Parser\Node\Stmt\Class_Method;
use Php_Parser\Node_Finder;
use Php_Stan\Analyser\Scope;
use Php_Stan\Better_Reflection\Reflection\ReflectionMethod;
use Php_Stan\Parser\Parser;
use Php_Stan\Php_Doc\Resolved_Php_Doc_Block;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Php_Doc_Tag_Node;
use Php_Stan\Reflection\Class_Reflection;
use Php_Stan\Reflection\Missing_Method_From_Reflection_Exception;
use Php_Stan\Reflection\Reflection_Provider;
use Php_Stan\Rules\Identifier_Rule_Error;
use Php_Stan\Rules\Rule_Error_Builder;
use Php_Stan\Type\File_Type_Mapper;
use function preg_match;
use function sprintf;
class Data_Provider_Helper
{
    private Reflection_Provider $reflection_provider;
    private File_Type_Mapper $file_type_mapper;
    private Parser $parser;
    private Php_Unit_Version $php_unit_version;
    public function __construct(Reflection_Provider $reflection_provider, File_Type_Mapper $file_type_mapper, Parser $parser, Php_Unit_Version $php_unit_version)
    {
        $this->reflection_provider = $reflection_provider;
        $this->file_type_mapper = $file_type_mapper;
        $this->parser = $parser;
        $this->php_unit_version = $php_unit_version;
    }
    /**
     * @param ReflectionMethod|ClassMethod $testMethod
     *
     * @return iterable<array{ClassReflection|null, string, int}>
     */
    public function get_data_provider_methods(Scope $scope, $test_method, Class_Reflection $class_reflection): iterable
    {
        yield from $this->yield_data_provider_annotations($test_method, $scope, $class_reflection);
        if (!$this->php_unit_version->supports_data_provider_attribute()->yes()) {
            return;
        }
        yield from $this->yield_data_provider_attributes($test_method, $class_reflection);
    }
    /**
     * @return array<PhpDocTagNode>
     */
    private function get_data_provider_annotations(?Resolved_Php_Doc_Block $php_doc): array
    {
        if ($php_doc === null) {
            return [];
        }
        $php_doc_nodes = $php_doc->get_php_doc_nodes();
        $annotations = [];
        foreach ($php_doc_nodes as $doc_node) {
            $annotations = array_merge($annotations, $doc_node->get_tags_by_name('@dataProvider'));
        }
        return $annotations;
    }
    /**
     * @return list<IdentifierRuleError> errors
     */
    public function process_data_provider(string $data_provider_value, ?Class_Reflection $class_reflection, string $method_name, int $line_number, bool $check_function_name_case, bool $deprecation_rules_installed): array
    {
        if ($class_reflection === null) {
            return [Rule_Error_Builder::message(sprintf('@dataProvider %s related class not found.', $data_provider_value))->line($line_number)->identifier('phpunit.dataProviderClass')->build()];
        }
        try {
            $data_provider_method_reflection = $class_reflection->get_native_method($method_name);
        } catch (Missing_Method_From_Reflection_Exception $missing_method_from_reflection_exception) {
            return [Rule_Error_Builder::message(sprintf('@dataProvider %s related method not found.', $data_provider_value))->line($line_number)->identifier('phpunit.dataProviderMethod')->build()];
        }
        $errors = [];
        if ($check_function_name_case && $method_name !== $data_provider_method_reflection->get_name()) {
            $errors[] = Rule_Error_Builder::message(sprintf('@dataProvider %s related method is used with incorrect case: %s.', $data_provider_value, $data_provider_method_reflection->get_name()))->line($line_number)->identifier('method.nameCase')->build();
        }
        if (!$data_provider_method_reflection->is_public()) {
            $errors[] = Rule_Error_Builder::message(sprintf('@dataProvider %s related method must be public.', $data_provider_value))->line($line_number)->identifier('phpunit.dataProviderPublic')->build();
        }
        if ($deprecation_rules_installed && $this->php_unit_version->requires_static_data_providers()->yes() && !$data_provider_method_reflection->is_static()) {
            $error_builder = Rule_Error_Builder::message(sprintf('@dataProvider %s related method must be static in PHPUnit 10 and newer.', $data_provider_value))->line($line_number)->identifier('phpunit.dataProviderStatic');
            $data_provider_method_reflection_declaring_class = $data_provider_method_reflection->get_declaring_class();
            if ($data_provider_method_reflection_declaring_class->get_file_name() !== null) {
                $stmts = $this->parser->parse_file($data_provider_method_reflection_declaring_class->get_file_name());
                $node_finder = new Node_Finder();
                /** @var ClassMethod|null $methodNode */
                $method_node = $node_finder->find_first($stmts, static fn($node): bool => $node instanceof Class_Method && $node->name->to_string() === $data_provider_method_reflection->get_name());
                if ($method_node !== null) {
                    $error_builder->fix_node($method_node, static function (Class_Method $method_node): \Php_Parser\Node\Stmt\Class_Method {
                        $method_node->flags |= Modifiers::STATIC;
                        return $method_node;
                    });
                }
            }
            $errors[] = $error_builder->build();
        }
        return $errors;
    }
    private function get_data_provider_annotation_value(Php_Doc_Tag_Node $php_doc_tag): ?string
    {
        if (preg_match('/^[^ \t]+/', (string) $php_doc_tag->value, $matches) !== 1) {
            return null;
        }
        return $matches[0];
    }
    /**
     * @return array{ClassReflection|null, string}
     */
    private function parse_data_provider_annotation_value(Scope $scope, string $data_provider_value): array
    {
        $parts = explode('::', $data_provider_value, 2);
        if (count($parts) <= 1) {
            return [$scope->get_class_reflection(), $data_provider_value];
        }
        if ($this->reflection_provider->has_class($parts[0])) {
            return [$this->reflection_provider->get_class($parts[0]), $parts[1]];
        }
        return [null, $data_provider_value];
    }
    /**
     * @return array<string, array{(ClassReflection|null), string, int}>|null
     */
    private function parse_data_provider_external_attribute(Attribute $attribute): ?array
    {
        if (count($attribute->args) !== 2) {
            return null;
        }
        $method_name_arg = $attribute->args[1]->value;
        if (!$method_name_arg instanceof String_) {
            return null;
        }
        $class_name_arg = $attribute->args[0]->value;
        if ($class_name_arg instanceof Class_Const_Fetch && $class_name_arg->class instanceof Name) {
            $class_name = $class_name_arg->class->to_string();
        } elseif ($class_name_arg instanceof String_) {
            $class_name = $class_name_arg->value;
        } else {
            return null;
        }
        $data_provider_class_reflection = null;
        if ($this->reflection_provider->has_class($class_name)) {
            $data_provider_class_reflection = $this->reflection_provider->get_class($class_name);
            $class_name = $data_provider_class_reflection->get_name();
        }
        return [sprintf('%s::%s', $class_name, $method_name_arg->value) => [$data_provider_class_reflection, $method_name_arg->value, $attribute->get_start_line()]];
    }
    /**
     * @return array<string, array{ClassReflection, string, int}>|null
     */
    private function parse_data_provider_attribute(Attribute $attribute, Class_Reflection $class_reflection): ?array
    {
        if (count($attribute->args) !== 1) {
            return null;
        }
        $method_name_arg = $attribute->args[0]->value;
        if (!$method_name_arg instanceof String_) {
            return null;
        }
        return [$method_name_arg->value => [$class_reflection, $method_name_arg->value, $attribute->get_start_line()]];
    }
    /**
     * @param ReflectionMethod|ClassMethod $node
     *
     * @return iterable<array{ClassReflection|null, string, int}>
     */
    private function yield_data_provider_attributes($node, Class_Reflection $class_reflection): iterable
    {
        if ($node instanceof ReflectionMethod) {
            foreach ($node->get_attributes_by_name('PHPUnit\Framework\Attributes\DataProvider') as $attr) {
                $args = $attr->get_arguments();
                if (count($args) !== 1) {
                    continue;
                }
                $start_line = $node->get_start_line();
                yield [$class_reflection, $args[0], $start_line];
            }
            return;
        }
        foreach ($node->attr_groups as $attr_group) {
            foreach ($attr_group->attrs as $attr) {
                $data_provider_method = null;
                if ($attr->name->to_lower_string() === 'phpunit\framework\attributes\dataprovider') {
                    $data_provider_method = $this->parse_data_provider_attribute($attr, $class_reflection);
                } elseif ($attr->name->to_lower_string() === 'phpunit\framework\attributes\dataproviderexternal') {
                    $data_provider_method = $this->parse_data_provider_external_attribute($attr);
                }
                if ($data_provider_method === null) {
                    continue;
                }
                yield from $data_provider_method;
            }
        }
    }
    /**
     * @param ReflectionMethod|ClassMethod $node
     *
     * @return iterable<array{ClassReflection|null, string, int}>
     */
    private function yield_data_provider_annotations($node, Scope $scope, Class_Reflection $class_reflection): iterable
    {
        $doc_comment = $node->get_doc_comment();
        if ($doc_comment === null) {
            return;
        }
        $method_php_doc = $this->file_type_mapper->get_resolved_php_doc($scope->get_file(), $class_reflection->get_name(), $scope->is_in_trait() ? $scope->get_trait_reflection()->get_name() : null, $node instanceof Class_Method ? $node->name->to_string() : $node->get_name(), $doc_comment instanceof Doc ? $doc_comment->get_text() : $doc_comment);
        foreach ($this->get_data_provider_annotations($method_php_doc) as $annotation) {
            $data_provider_value = $this->get_data_provider_annotation_value($annotation);
            if ($data_provider_value === null) {
                // Missing value is already handled in NoMissingSpaceInMethodAnnotationRule
                continue;
            }
            $start_line = $node->get_start_line();
            $data_provider_method = $this->parse_data_provider_annotation_value($scope, $data_provider_value);
            $data_provider_method[] = $start_line;
            yield $data_provider_value => $data_provider_method;
        }
    }
}