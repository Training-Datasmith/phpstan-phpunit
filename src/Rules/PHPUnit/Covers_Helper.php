<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Php_Unit;

use function array_merge;
use function explode;
use Php_Parser\Node;
use Php_Parser\Node\Name;
use Php_Stan\Php_Doc\Resolved_Php_Doc_Block;
use Php_Stan\Php_Doc_Parser\Ast\Php_Doc\Php_Doc_Tag_Node;
use Php_Stan\Reflection\Reflection_Provider;
use Php_Stan\Rules\Identifier_Rule_Error;
use Php_Stan\Rules\Rule_Error_Builder;
use function sprintf;
use function strpos;
class Covers_Helper
{
    /**
     * Reflection provider.
     *
     */
    private Reflection_Provider $reflection_provider;
    public function __construct(Reflection_Provider $reflection_provider)
    {
        $this->reflection_provider = $reflection_provider;
    }
    /**
     * Gathers @covers and @coversDefaultClass annotations from phpdocs.
     *
     * @return array{PhpDocTagNode[], PhpDocTagNode[]}
     */
    public function get_cover_annotations(?Resolved_Php_Doc_Block $php_doc): array
    {
        if ($php_doc === null) {
            return [[], []];
        }
        $php_doc_nodes = $php_doc->get_php_doc_nodes();
        $covers = [];
        $covers_default_classes = [];
        foreach ($php_doc_nodes as $doc_node) {
            $covers = array_merge($covers, $doc_node->get_tags_by_name('@covers'));
            $covers_default_classes = array_merge($covers_default_classes, $doc_node->get_tags_by_name('@coversDefaultClass'));
        }
        return [$covers, $covers_default_classes];
    }
    /**
     * @return list<IdentifierRuleError> errors
     */
    public function process_covers(Node $node, Php_Doc_Tag_Node $php_doc_tag, ?Php_Doc_Tag_Node $covers_default_class): array
    {
        $errors = [];
        $covers = (string) $php_doc_tag->value;
        if ($covers === '') {
            $errors[] = Rule_Error_Builder::message('@covers value does not specify anything.')->identifier('phpunit.covers')->build();
            return $errors;
        }
        $is_method = strpos($covers, '::') !== false;
        $full_name = $covers;
        if ($is_method) {
            [$class_name, $method] = explode('::', $covers);
        } else {
            $class_name = $covers;
        }
        if ($class_name === '' && $node instanceof Node\Stmt\Class_Method && $covers_default_class !== null) {
            $class_name = (string) $covers_default_class->value;
            $full_name = $class_name . $covers;
        }
        if ($this->reflection_provider->has_class($class_name)) {
            $class = $this->reflection_provider->get_class($class_name);
            if ($class->is_interface()) {
                $errors[] = Rule_Error_Builder::message(sprintf('@covers value %s references an interface.', $full_name))->identifier('phpunit.coversInterface')->build();
            }
            if (isset($method) && $method !== '' && !$class->has_method($method)) {
                $errors[] = Rule_Error_Builder::message(sprintf('@covers value %s references an invalid method.', $full_name))->identifier('phpunit.coversMethod')->build();
            }
        } elseif (isset($method) && $this->reflection_provider->has_function(new Name($method, []), null)) {
            return $errors;
        } elseif (!isset($method) && $this->reflection_provider->has_function(new Name($class_name, []), null)) {
            return $errors;
        } else {
            $error = Rule_Error_Builder::message(sprintf('@covers value %s references an invalid %s.', $full_name, $is_method ? 'method' : 'class or function'))->identifier(sprintf('phpunit.covers%s', $is_method ? 'Method' : ''));
            if (strpos($class_name, '\\') === false) {
                $error->tip('The @covers annotation requires a fully qualified name.');
            }
            $errors[] = $error->build();
        }
        return $errors;
    }
}