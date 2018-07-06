<?php
declare(strict_types=1);

namespace Dallgoot\Yaml;

use Dallgoot\Yaml\Node as   Node;
use Dallgoot\Yaml\Types as T;
use Dallgoot\Yaml\YamObject;
use Dallgoot\Yaml\Tag;

class Loader
{
    public $errors = [];
    //options
    public const EXCLUDE_DIRECTIVES = 0001;//DONT include_directive
    public const IGNORE_COMMENTS    = 0010;//DONT include_comments
    public const EXCEPTIONS_PARSING = 0100;//THROW Exception on parsing Errors
    public const NO_OBJECT_FOR_DATE = 1000;//DONT import date strings as dateTime Object
    //
    private $_content;
    private $filePath;
    private $_debug   = 0;//TODO: determine levels
    private $_options = 0;
    //Errors
    const ERROR_NO_KEYNAME = self::class.": key has NO NAME on line %d";
    const INVALID_DOCUMENT = self::class.": DOCUMENT %d can NOT be a mapping AND a sequence";
    //Exceptions
    const EXCEPTION_NO_FILE    = self::class.": file '%s' does not exists (or path is incorrect?)";
    const EXCEPTION_READ_ERROR = self::class.": file '%s' failed to be loaded (permission denied ?)";
    const EXCEPTION_LINE_SPLIT = self::class.": content is not a string(maybe a file error?)";

    public function __construct($absolutePath = null, $options = null, $debug = 0)
    {
        $this->_debug = is_int($debug) ? min($debug, 3) : 1;
        if (!is_null($options)) {
            $this->options = $options;
        }
        if (!is_null($absolutePath)) {
            $this->load($absolutePath);
        }
    }

    public function load(String $absolutePath):Loader
    {
        $this->_debug && var_dump($absolutePath);
        $this->filePath = $absolutePath;
        if (!file_exists($absolutePath)) {
            throw new \Exception(sprintf(self::EXCEPTION_NO_FILE, $absolutePath));
        }
        $adle = "auto_detect_line_endings";
        $prevADLE = ini_get($adle);
        !$prevADLE && ini_set($adle, "true");
        $content = file($absolutePath, FILE_IGNORE_NEW_LINES);
        !$prevADLE && ini_set($adle, "false");
        if (is_bool($content)) {
            throw new \Exception(sprintf(self::EXCEPTION_READ_ERROR, $absolutePath));
        }
        $this->_content = $content;
        return $this;
    }

    /**
     * Parse Yaml lines into an hierarchy of Node
     *
     * @param      string       $strContent  The Yaml string or null to parse loaded content
     * @throws     \Exception    if content is not available as $strContent or as $this->content (from file)
     * @throws     \ParseError  if any error during parsing or building
     *
     * @return     array      the hierarchy built = an array of YamlObject
     */
    public function parse($strContent = null):array
    {
        $source = is_null($strContent) ? $this->_content :
                                    preg_split("/([^\n\r]+)/um", $strContent, null, PREG_SPLIT_DELIM_CAPTURE);
        //TODO : be more permissive on $strContent values
        if (!is_array($source)) throw new \Exception(self::EXCEPTION_LINE_SPLIT);
        $previous = $root = new Node();
        $emptyLines = [];
        $specialTypes = [T::LITTERAL, T::LITTERAL_FOLDED, T::EMPTY];
        foreach ($source as $lineNb => $lineString) {
            $n = new Node($lineString, $lineNb + 1);//TODO: useful???-> $this->_debug && var_dump($n);
            $parent = $previous;
            $deepest = $previous->getDeepestNode();
            if ($deepest->type === T::PARTIAL ||
                ($deepest->value instanceof \SplQueue && $deepest->value->top()->type === T::PARTIAL)) {
                $target = $deepest->type === T::PARTIAL ? $deepest : $deepest->value->top();
                $target->parse($target->value.$lineString);
            } else {
                if (in_array($n->type, $specialTypes)) {
                    if ($this->_onSpecialType($n, $parent, $previous, $emptyLines)) continue;
                }
                foreach ($emptyLines as $key => $node) {
                    $node->getParent()->add($node);
                }
                $emptyLines = [];
                if ($n->indent === 0) {
                    $parent = $root;
                } elseif ($n->indent < $previous->indent) {
                    $parent = $previous->getParent($n->indent);
                } elseif ($n->indent === $previous->indent) {
                    $parent = $previous->getParent();
                } elseif ($n->indent > $previous->indent) {
                    if ($this->_onDeepestType($n, $parent, $previous, $lineString)) continue;
                }
                $parent->add($n);
                $previous = $n;
            }
        }
        if ($this->_debug > 2) {
            var_dump("\033[33mParsed Structure\033[0m\n", $root);
            exit(0);
        }
        try {
            $out = $this->_buildFile($root);
        } catch (\Error|\Exception $e) {
            var_dump($root);
            throw new \ParseError($e->getMessage()." on line ".$e->getLine());
        }
        return $out;
    }

    private function _onSpecialType(&$n, &$parent, &$previous, &$emptyLines):bool
    {
        $deepest = $previous->getDeepestNode();
        switch ($n->type) {
            case T::LITTERAL://fall through
            case T::LITTERAL_FOLDED:
                $deepestParent = $deepest->getParent();
                if ($deepest->type === T::EMPTY && $deepestParent->type === T::KEY) {
                    $parent = $deepestParent;
                }
                break;
            case T::EMPTY:
                if ($previous->type === T::STRING) $emptyLines[] = $n->setParent($previous->getParent());
                if (in_array($deepest->type, T::$LITTERALS)) $emptyLines[] = $n->setParent($deepest);
                return true;
                break;
            default://do nothing
                break;
        }
        return false;
    }

    private function _onDeepestType(&$n, &$parent, &$previous, $lineString):bool
    {
        $deepest = $previous->getDeepestNode();
        switch ($deepest->type) {
            case T::REF_DEF://fall through
            case T::SET_VALUE://fall through
            case T::TAG:
                $parent = $deepest;
                break;
            case T::LITTERAL:
            case T::LITTERAL_FOLDED:
                $n->type = T::STRING;
                $n->value = trim($lineString);
                unset($n->name);
                $parent = $deepest;
                break;
            case T::EMPTY:
            case T::STRING:
                if ($n->type === T::STRING &&
                    !in_array($deepest->getParent()->type, T::$LITTERALS) ) {
                    $deepest->type = T::STRING;
                    $deepest->value .= PHP_EOL.$n->value;
                    return true;
                } else {
                    if (!in_array($previous->type, [T::ITEM, T::SET_KEY])) {
                        $parent = $deepest->getParent();
                    }
                }
        }
        return false;
    }

    private function _build(object $node, $root = null, &$parent = null)
    {
        return $node instanceof \SplQueue ?
                    $this->_buildQueue($node, $root, $parent) : $this->_buildNode($node, $root, $parent);
    }

    private function _buildQueue(\SplQueue $node, $root, &$parent)
    {
        $type = property_exists($node, "type") ? $node->type : null;
        if (is_object($parent) && $parent instanceof YamlObject) {
            $p = $parent;
        } else {
            switch ($type) {
                case T::MAPPING: //fall through
                case T::SET:  $p = new \StdClass;break;
                case T::SEQUENCE: $p = [];break;
                case T::KEY: $p = $parent;break;
            }
        }
        if (in_array($type, T::$LITTERALS)) {
            return $this->_litteral($node, $type);
        }
        foreach ($node as $key => $child) {
            $result = $this->_build($child, $root, $p);
            if (is_string($result)) {
                if ($p instanceof YamlObject) {
                    $p->setText($result);
                } else {
                    $p .= $result;
                }
            }
        }
        return $p;
    }

    private function _buildNode(Node $node, $root, &$parent)
    {
        $line  = property_exists($node, "line") ? $node->line : null;
        $name  = property_exists($node, "name") ? $node->name : null;
        $value = $node->value;
        $type  = $node->type;
        switch ($type) {
            case T::KEY:  $this->_buildKey($node, $root, $parent);return;
            case T::ITEM: $this->_buildItem($value, $root, $parent);return;
            case T::DIRECTIVE: return;//TODO
            case T::TAG:
                return is_null($value) ? new Tag($name, null) :
                                         new Tag($name, $this->_build($value, $root, $parent));
            case T::COMMENT: $root->addComment($line, $value);
                return;
            case T::REF_DEF: //fall through
            case T::REF_CALL:
                $tmp = is_object($value) ? $this->_build($value, $root, $parent) : $node->getPhpValue();
                if ($type === T::REF_DEF) $root->addReference($name, $tmp);
                return $root->getReference($name);
            case T::SET_KEY: $key = json_encode($this->_build($value, $root, $parent));
                if(empty($key)) throw new Exception("Cant determine ".var_export($value,true), 1);
                $parent->{$key} = null;
                return;
            case T::SET_VALUE:
                $prop = array_keys(get_object_vars($parent));
                $key = end($prop);
                if (property_exists($value, "type") && in_array($value->type, [T::ITEM, T::MAPPING])) {
                    switch ($value->type) {
                        case T::ITEM:$p = [];break;
                        default:$p = new \StdClass;
                    }
                    $this->_build($value, $root, $p);
                } else {
                    $p = $this->_build($value, $root, $parent->{$key});
                }
                $parent->{$key} = $p;
                return;
            default:
                return is_object($value) ? $this->_build($value, $root, $parent) : $node->getPhpValue();
        }
    }

    private function _buildKey($node, $root, &$parent):void
    {
        if (is_null($node->name)) {
            $this->_error(sprintf(self::ERROR_NO_KEYNAME, $node->line, $this->filePath));
        } else {
            $parent->{$node->name} = $this->_build($node->value, $root, $parent->{$node->name});
        }
    }

    private function _buildItem($value, $root, &$parent):void
    {
        $index = ($value instanceof Node && $value->type === T::KEY) ? $value->name : count($parent);
        $parent[$index] = $this->_build($value, $root, $parent[$index]);
    }

    /**
     * Builds a file.  check multiple documents & split if more than one documents
     *
     * @param      Node   $root   The root node
     * @return     array  representing the total of documents in the file.
     */
    private function _buildFile(Node $root):array
    {
        $totalDocStart = 0;
        $documents = [];
        $root->value->setIteratorMode(\SplDoublyLinkedList::IT_MODE_DELETE);
        foreach ($root->value as $key => $child) {
            if ($child->type === T::DOC_START) {
                $totalDocStart++;
            }
            //if 0 or 1 DOC_START = we are still in first document
            $currentDoc = $totalDocStart > 1 ? $totalDocStart - 1 : 0;
            if (!array_key_exists($currentDoc, $documents))
                $documents[$currentDoc] = new \SplQueue();
            $documents[$currentDoc]->enqueue($child);
        }
        $this->_debug >= 2 && var_dump($documents);
        return array_map([$this, '_buildDocument'], $documents, array_keys($documents));
    }

    private function _buildDocument(\SplQueue $queue, $key):YamlObject
    {
        $doc = new YamlObject();
        $childTypes = $this->_getChildrenTypes($queue);
        $isMapping  = count(array_intersect($childTypes, [T::KEY, T::MAPPING])) > 0;
        $isSequence = in_array(T::ITEM, $childTypes);
        $isSet      = in_array(T::SET_VALUE, $childTypes);
        if ($isMapping && $isSequence) {
            $this->_error(sprintf(self::INVALID_DOCUMENT, $key));
        } else {
            switch (true) {
                case $isSequence: $queue->type = T::SEQUENCE;break;
                case $isSet: $queue->type = T::SET;break;
                case $isMapping:
                default:$queue->type = T::MAPPING;
            }
        }
        $this->_debug >= 3 && var_dump($doc, $queue);
        return $this->_build($queue, $doc, $doc);
    }

    private function _litteral(\SplQueue $children, $type):string
    {
        try {
            $output = '';
            $children->rewind();
            $refIndent = $children->current()->indent;
            if ($type === T::LITTERAL_FOLDED) {
                $separator = ' ';
                $action = function ($c) use ($refIndent) {
                    return $c->indent > $refIndent || $c->type === T::EMPTY ? PHP_EOL.$c->value : $c->value;
                };
            } else {
                $separator = PHP_EOL;
                $action = function ($c) { return $c->value; };
            }
            $tmp = [];
            $children->rewind();
            foreach ($children as $key => $child) {
                $tmp[]= $action($child);
            }
            $output = implode($separator, $tmp);
        } catch (\Error $err) {
            $this->error($err->getMessage());
        }
        return $output;
    }

    private function _getChildrenTypes(\SplQueue $children):array
    {
        $types = [];
        foreach ($children as $key => $child) {
            $types[] = $child->type;
        }
        return array_unique($types);
    }

    public function _error($message)
    {
        if ($this->_options & self::EXCEPTIONS_PARSING)
            throw new \ParseError($message." for '$this->filePath'", 1);
        $this->errors[] = $message;
    }
}
