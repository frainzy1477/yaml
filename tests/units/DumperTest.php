<?php

namespace Test\Dallgoot\Yaml;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use Dallgoot\Yaml\Dumper;
use Dallgoot\Yaml\YamlObject;

/**
 * Class DumperTest.
 *
 * @author Stephane Rebai <stephane.rebai@gmail.com>.
 * @license https://opensource.org/licenses/MIT The MIT license.
 * @link https://github.com/dallgoot/yaml
 * @since File available since Release 1.0.0
 *
 * @covers \Dallgoot\Yaml\Dumper
 */
class DumperTest extends TestCase
{
    /**
     * @var Dumper $dumper An instance of "Dumper" to test.
     */
    private $dumper;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        /** @todo Maybe add some arguments to this constructor */
        $this->dumper = new Dumper();
    }

    /**
     * @covers \Dallgoot\Yaml\Dumper::toString
     */
    public function testToString(): void
    {
        /** @todo Complete this unit test method. */
        $this->markTestIncomplete();
    }

    /**
     * @covers \Dallgoot\Yaml\Dumper::toFile
     */
    public function testToFile(): void
    {
        /** @todo Complete this unit test method. */
        $this->markTestIncomplete();
    }

}
