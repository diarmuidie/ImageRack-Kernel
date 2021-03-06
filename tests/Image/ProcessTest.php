<?php

namespace Diarmuidie\ImageRack\Image;

use Mockery as Mockery;

class ProcessTest extends \PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        Mockery::close();
    }

    public function testProcessCallesTemplate()
    {
        $image = Mockery::mock('\Intervention\Image\Image')
            ->shouldReceive('isEncoded')->andReturn(true)->once()
            ->mock();
        $imageManager = Mockery::mock('\Intervention\Image\ImageManager')
            ->shouldReceive('make')->andReturn($image)->once()
            ->mock();
        $template = Mockery::mock('\Diarmuidie\ImageRack\Image\TemplateInterface')
            ->shouldReceive('process')->andReturn($image)->once()
            ->mock();
        $resource = Mockery::type('resource');

        $process = new Process($resource, $imageManager);

        $processed = $process->process($template);

        $this->assertInstanceOf('\Intervention\Image\Image', $processed);
    }

    public function testProcessCallesEncode()
    {
        $image = Mockery::mock('\Intervention\Image\Image')
            ->shouldReceive('isEncoded')->andReturn(false)->once()
            ->shouldReceive('encode')->once()
            ->mock();
        $imageManager = Mockery::mock('\Intervention\Image\ImageManager')
            ->shouldReceive('make')->andReturn($image)->once()
            ->mock();
        $template = Mockery::mock('\Diarmuidie\ImageRack\Image\TemplateInterface')
            ->shouldReceive('process')->andReturn($image)->once()
            ->mock();
        $resource = Mockery::type('resource');

        $process = new Process($resource, $imageManager);

        $processed = $process->process($template);
    }
}
