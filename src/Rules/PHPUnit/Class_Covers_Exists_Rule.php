<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Php_Unit;

use function array_merge;
use function array_shift;
use function count;
use Php_Parser\Node;
use Php_Stan\Analyser\Scope;
use Php_Stan\Node\In_Class_Node;
use Php_Stan\Reflection\Reflection_Provider;
use Php_Stan\Rules\Rule;
use Php_Stan\Rules\Rule_Error_Builder;
use Php_Unit\Framework\Test_Case;
use function sprintf;
/**
 * @implements Rule<InClassNode>
 */
class Class_Covers_Exists_Rule implements Rule
{
    /**
     * Covers helper.
     *
     */
    private Covers_Helper $covers_helper;
    /**
     * Reflection provider.
     *
     */
    private Reflection_Provider $reflection_provider;
    public function __construct(Covers_Helper $covers_helper, Reflection_Provider $reflection_provider)
    {
        $this->reflection_provider = $reflection_provider;
        $this->covers_helper = $covers_helper;
    }
    public function get_node_type(): string
    {
        return In_Class_Node::class;
    }
    public function process_node(Node $node, Scope $scope): array
    {
        $class_reflection = $node->get_class_reflection();
        if (!$class_reflection->is(Test_Case::class)) {
            return [];
        }
        $class_php_doc = $class_reflection->get_resolved_php_doc();
        [$class_covers, $class_covers_default_classes] = $this->covers_helper->get_cover_annotations($class_php_doc);
        if (count($class_covers_default_classes) >= 2) {
            return [Rule_Error_Builder::message(sprintf('@coversDefaultClass is defined multiple times.'))->identifier('phpunit.coversDuplicate')->build()];
        }
        $errors = [];
        $covers_default_class = array_shift($class_covers_default_classes);
        if ($covers_default_class !== null) {
            $class_name = (string) $covers_default_class->value;
            if (!$this->reflection_provider->has_class($class_name)) {
                $errors[] = Rule_Error_Builder::message(sprintf('@coversDefaultClass references an invalid class %s.', $class_name))->identifier('phpunit.coversClass')->build();
            }
        }
        foreach ($class_covers as $covers) {
            $errors = array_merge($errors, $this->covers_helper->process_covers($node, $covers, null));
        }
        return $errors;
    }
}