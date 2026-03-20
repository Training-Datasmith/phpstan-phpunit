<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Php_Unit;

use Php_Parser\Node;
use Php_Stan\Analyser\Scope;
use Php_Stan\Node\In_Class_Method_Node;
use Php_Stan\Rules\Rule;
use Php_Unit\Framework\Test_Case;
/**
 * @implements Rule<InClassMethodNode>
 */
class No_Missing_Space_In_Method_Annotation_Rule implements Rule
{
    /**
     * Covers helper.
     *
     */
    private Annotation_Helper $annotation_helper;
    public function __construct(Annotation_Helper $annotation_helper)
    {
        $this->annotation_helper = $annotation_helper;
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
        $doc_comment = $node->get_doc_comment();
        if ($doc_comment === null) {
            return [];
        }
        return $this->annotation_helper->process_doc_comment($doc_comment);
    }
}