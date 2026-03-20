<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Php_Unit;

use function in_array;
use Php_Parser\Comment\Doc;
use Php_Stan\Rules\Identifier_Rule_Error;
use Php_Stan\Rules\Rule_Error_Builder;
use function preg_match;
use function preg_split;
class Annotation_Helper
{
    private const ANNOTATIONS_WITH_PARAMS = ['backupGlobals', 'backupStaticAttributes', 'covers', 'coversDefaultClass', 'dataProvider', 'depends', 'group', 'preserveGlobalState', 'requires', 'testDox', 'testWith', 'ticket', 'uses'];
    /**
     * @return list<IdentifierRuleError> errors
     */
    public function process_doc_comment(Doc $doc_comment): array
    {
        $errors = [];
        $doc_comment_lines = preg_split("/((\r?\n)|(\r\n?))/", $doc_comment->get_text());
        if ($doc_comment_lines === false) {
            return [];
        }
        foreach ($doc_comment_lines as $doc_comment_line) {
            // These annotations can't be retrieved using the getResolvedPhpDoc method on the FileTypeMapper as they are not present when they are invalid
            $annotation = preg_match('/(?<annotation>@(?<property>[a-zA-Z]+)(?<whitespace>\s*)(?<value>.*))/', $doc_comment_line, $matches);
            if ($annotation === false || $matches === []) {
                continue;
                // Line without annotation
            }
            if (!in_array($matches['property'], self::ANNOTATIONS_WITH_PARAMS, true)) {
                continue;
            }
            if ($matches['whitespace'] !== '') {
                continue;
            }
            $errors[] = Rule_Error_Builder::message('Annotation "' . $matches['annotation'] . '" is invalid, "@' . $matches['property'] . '" should be followed by a space and a value.')->identifier('phpunit.invalidPhpDoc')->build();
        }
        return $errors;
    }
}