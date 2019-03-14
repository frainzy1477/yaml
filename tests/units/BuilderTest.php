<?php

namespace Test\Dallgoot\Yaml;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Dallgoot\Yaml\Builder;
use Dallgoot\Yaml\YamlObject;
use Dallgoot\Yaml\NodeList;
use Dallgoot\Yaml\Node;
use Dallgoot\Yaml\NodeBlank;
use Dallgoot\Yaml\NodeDocStart;
use Dallgoot\Yaml\NodeDocEnd;
use Dallgoot\Yaml\NodeItem;
use Dallgoot\Yaml\NodeKey;
use Dallgoot\Yaml\NodeRoot;
use Dallgoot\Yaml\NodeScalar;
use Dallgoot\Yaml\NodeSetKey;

/**
 * Class BuilderTest.
 *
 * @author Stephane Rebai <stephane.rebai@gmail.com>.
 * @license https://opensource.org/licenses/MIT The MIT license.
 * @link https://github.com/john-doe/my-awesome-project
 * @since File available since Release 1.0.0
 *
 * @covers \Dallgoot\Yaml\Builder
 */
class BuilderTest extends TestCase
{
    /**
     * @var Builder $builder An instance of "Builder" to test.
     */
    private $builder;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        /** @todo Maybe add some arguments to this constructor */
        $this->builder = new Builder();
    }

    private function buildSimpleMapping()
    {
        // create a yaml mapping
        $root = new NodeRoot;
        $root->add(new NodeKey('key: value', 1));
        return $this->builder::buildContent($root);
    }

    private function buildSimpleSequence()
    {
        // create a yaml sequence
        $root = new NodeRoot;
        $root->add(new NodeItem('- itemvalue', 1));
        return $this->builder::buildContent($root);
    }

    private function buildMultiDoc()
    {
        $root = new NodeRoot;
        $root->add(new NodeDocStart('---', 1));
        $root->add(new NodeKey('key: value', 2));
        $root->add(new NodeDocEnd('...', 3));
        $root->add(new NodeDocStart('---', 4));
        $root->add(new NodeKey('key: value', 5));
        $root->add(new NodeDocStart('---', 6));
        $root->add(new NodeKey('key: value', 7));
        return $this->builder::buildContent($root);
    }

    /**
     * @covers \Dallgoot\Yaml\Builder::buildContent
     * @todo   test :
     *  simple literal
     *  only JSON content
     *  multidocument
     */
    public function testBuildContent(): void
    {
        ob_start();
        $this->assertEquals($this->builder::buildContent(new NodeRoot, 2), null);
        ob_end_clean();
        //test simple mapping
        $yamlMapping = $this->buildSimpleMapping();
        $this->assertTrue($yamlMapping instanceof YamlObject);
        $this->assertTrue(property_exists($yamlMapping, 'key'));
        $this->assertEquals($yamlMapping->key, 'value');
        //test simple sequence
        $yamlSequence = $this->buildSimpleSequence();
        $this->assertTrue($yamlSequence instanceof YamlObject);
        $this->assertArrayHasKey(0, $yamlSequence);
        $this->assertEquals($yamlSequence[0], 'itemvalue');
        // test multi document
        $multiDoc = $this->buildMultiDoc();
        $this->assertTrue(is_array($multiDoc));
        $this->assertTrue(count($multiDoc) === 3);
        $this->assertArrayHasKey(0, $multiDoc);
        $this->assertTrue($multiDoc[0] instanceof YamlObject);
        $this->assertArrayHasKey(1, $multiDoc);
        $this->assertTrue($multiDoc[1] instanceof YamlObject);
        $this->assertArrayHasKey(2, $multiDoc);
        $this->assertTrue($multiDoc[2] instanceof YamlObject);
    }

    /**
     * @covers \Dallgoot\Yaml\Builder::buildContent
     * @todo   test :
     *  simple literal
     *  only JSON content
     *  multidocument
     */
    public function testBuildContentException(): void
    {
        $this->expectException(\Exception::class);
        $root = new NodeRoot;
        $root->value = null;
        $this->builder::buildContent($root);
    }

    /**
     * @covers \Dallgoot\Yaml\Builder::buildDocument
     */
    public function testBuildDocument(): void
    {
        $nodekey = new NodeKey('key: keyvalue', 1);
        $list = new NodeList($nodekey);
        $yamlObject = $this->builder->buildDocument($list, 0);
        $this->assertTrue($yamlObject instanceof YamlObject);
    }

    /**
     * @covers \Dallgoot\Yaml\Builder::buildDocument
     */
    public function testBuildDocumentDebug(): void
    {
        // ob_start();
        // ob_end_clean();
        $output = <<<'EOF'
Document #0
Dallgoot\Yaml\NodeRoot Object
(
    [line->indent] =>  -> -1
    [value] => Dallgoot\Yaml\NodeList Object
        (
            [type] => 
            [flags:SplDoublyLinkedList:private] => 0
            [dllist:SplDoublyLinkedList:private] => Array
                (
                )

        )

    [raw] => 
    [parent] => NO PARENT!!!
)

EOF;
        $debug = new \ReflectionProperty(Builder::class, '_debug');
        $debug->setAccessible(true);
        $debug->setValue(3);
        $list = new NodeList;
        $this->builder::buildDocument($list, 0);
        $this->expectOutputString($output);
        $debug->setValue(0);
    }

    /**
     * @covers \Dallgoot\Yaml\Builder::buildDocument
     */
    public function testBuildDocumentException(): void
    {
        $this->expectException(\ParseError::class);
        // $nodekey = new NodeDocStart('', 1);
        // $nodekey = new \StdClass;
        // $list = new NodeList($nodekey);
        $list = new NodeList();
        $list->push(new \StdClass);
        $yamlObject = $this->builder->buildDocument($list, 0);
    }
    /**
     * @covers \Dallgoot\Yaml\Builder::getScalar
     */
    public function testGetScalar(): void
    {
        $this->assertEquals($this->builder->getScalar('yes')  , true);
        $this->assertEquals($this->builder->getScalar('no')   , false);
        $this->assertEquals($this->builder->getScalar('true') , true);
        $this->assertEquals($this->builder->getScalar('false'), false);
        $this->assertEquals($this->builder->getScalar('null') , null);
        $this->assertEquals($this->builder->getScalar('.inf') , \INF);
        $this->assertEquals($this->builder->getScalar('-.inf'), -\INF);
        $this->assertTrue(is_nan($this->builder->getScalar('.nan')));
    }

    /**
     * @covers \Dallgoot\Yaml\Builder::getNumber
     */
    public function testGetNumber(): void
    {
        $reflector = new \ReflectionClass($this->builder);
        $method = $reflector->getMethod('getNumber');
        $method->setAccessible(true);
        $this->assertTrue(is_numeric($method->invoke(null, '132')));
        $this->assertTrue(is_numeric($method->invoke(null, '0x27')));
        $this->assertTrue(is_numeric($method->invoke(null, '0xaf')));
        $this->assertTrue(is_float($method->invoke(null, '132.123')));
        $this->assertFalse(is_float($method->invoke(null, '132.12.3')));
    }

    /**
     * @covers \Dallgoot\Yaml\Builder::pushAndSave
     */
    public function testPushAndSave(): void
    {
        $reflector = new \ReflectionClass($this->builder);
        $method = $reflector->getMethod('pushAndSave');
        $method->setAccessible(true);
        $child = new NodeBlank('', 1);
        $buffer = new NodeList;
        $documents = [];
        $this->assertTrue($buffer->count() === 0);
        $method->invokeArgs(null, [$child, &$buffer, &$documents]);
        $this->assertTrue($buffer->count() === 0);
        $this->assertTrue(count($documents) === 1);
    }

    /**
     * @covers \Dallgoot\Yaml\Builder::saveAndPush
     */
    public function testSaveAndPush(): void
    {
        $reflector = new \ReflectionClass($this->builder);
        $method = $reflector->getMethod('saveAndPush');
        $method->setAccessible(true);
        $itemNode = new NodeItem('- item', 1);
        $buffer = new NodeList($itemNode);
        $child = new NodeBlank('', 1);
        $documents = [];
        $this->assertTrue($buffer->count() === 1);
        $method->invokeArgs(null, [$child, &$buffer, &$documents]);
        $this->assertTrue($buffer->count() === 1);
        $this->assertTrue(count($documents) === 1);
        $this->assertTrue($buffer->offsetGet(0) instanceof NodeBlank);
    }
}
