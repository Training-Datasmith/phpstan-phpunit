<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Php_Unit;

use function array_map;
use function array_merge;
use function array_shift;
use function count;
use function in_array;
use Php_Parser\Node;
use Php_Stan\Analyser\Scope;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Php_Doc_Tag_Node;
use Php_Stan\Rules\Rule;
use Php_Stan\Rules\Rule_Error_Builder;
use Php_Stan\Type\File_Type_Mapper;
use Php_Unit\Framework\Test_Case;
use function sprintf;
/**
 * @implements Rule<Node\Stmt\ClassMethod>
 */
class Class_Method_Covers_Exists_Rule implements Rule
{
    /**
     * Covers helper.
     *
     */
    private Covers_Helper $covers_helper;
    /**
     * The file type mapper.
     *
     */
    private File_Type_Mapper $file_type_mapper;
    public function __construct(Covers_Helper $covers_helper, File_Type_Mapper $file_type_mapper)
    {
        $this->covers_helper = $covers_helper;
        $this->file_type_mapper = $file_type_mapper;
    }
    public function get_node_type(): string
    {
        return Node\Stmt\Class_Method::class;
    }
    public function process_node(Node $node, Scope $scope): array
    {
        $class_reflection = $scope->get_class_reflection();
        if ($class_reflection === null) {
            return [];
        }
        if (!$class_reflection->is(Test_Case::class)) {
            return [];
        }
        $class_php_doc = $class_reflection->get_resolved_php_doc();
        [$class_covers, $class_covers_default_classes] = $this->covers_helper->get_cover_annotations($class_php_doc);
        $class_covers_strings = array_map(static fn(Php_Doc_Tag_Node $covers): string => (string) $covers->value, $class_covers);
        $doc_comment = $node->get_doc_comment();
        if ($doc_comment === null) {
            return [];
        }
        $covers_default_class = count($class_covers_default_classes) === 1 ? array_shift($class_covers_default_classes) : null;
        $method_php_doc = $this->file_type_mapper->get_resolved_php_doc($scope->get_file(), $class_reflection->get_name(), $scope->is_in_trait() ? $scope->get_trait_reflection()->get_name() : null, $node->name->to_string(), $doc_comment->get_text());
        [$method_covers, $method_covers_default_classes] = $this->covers_helper->get_cover_annotations($method_php_doc);
        $errors = [];
        if (count($method_covers_default_classes) > 0) {
            $errors[] = Rule_Error_Builder::message(sprintf('@coversDefaultClass defined on class method %s.', $node->name))->identifier('phpunit.covers')->build();
        }
        foreach ($method_covers as $covers) {
            if (in_array((string) $covers->value, $class_covers_strings, true)) {
                $errors[] = Rule_Error_Builder::message(sprintf('Class already @covers %s so the method @covers is redundant.', $covers->value))->identifier('phpunit.coversDuplicate')->build();
            }
            $errors = array_merge($errors, $this->covers_helper->process_covers($node, $covers, $covers_default_class));
        }
        return $errors;
    }
}