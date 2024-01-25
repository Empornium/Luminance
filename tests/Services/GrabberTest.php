<?php

use Luminance\Core\Master;
use Luminance\Legacy\Collage;
use Luminance\Services\Grabber;
use PHPUnit\Framework\TestCase;

class GrabberTest extends TestCase
{
    private $grabber;

    protected function setUp(): void
    {
        $master = $this->getMockBuilder(Master::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->grabber = new Grabber($master);
    }

    public function testFilterResolution()
    {
        $this->grabber->filterResolution('1080p');
        $this->assertSame($this->grabber->resolution, '1080p');
        $this->grabber->filterResolution('0');
        $this->assertNull($this->grabber->resolution);
        $this->grabber->filterResolution('Invalid');
        $this->assertNull($this->grabber->resolution);
    }

    public function testFilterSource()
    {
        $this->grabber->filterSource('bluray');
        $this->assertSame($this->grabber->source, 'bluray');
        $this->grabber->filterSource('0');
        $this->assertNull($this->grabber->source);
        $this->grabber->filterSource('Invalid');
        $this->assertNull($this->grabber->source);
    }

    public function testFilterOrigin()
    {
        $this->grabber->filterOrigin('mixed');
        $this->assertSame($this->grabber->origin, 'mixed');
        $this->grabber->filterOrigin('0');
        $this->assertNull($this->grabber->origin);
        $this->grabber->filterOrigin('Invalid');
        $this->assertNull($this->grabber->origin);
    }

    public function testFilenameMaxLength()
    {
        $title = str_repeat('A', 400);
        $this->assertEquals(200, strlen($this->grabber->getFilename($title)));
    }
}
