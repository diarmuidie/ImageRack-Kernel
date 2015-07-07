<?php

namespace Diarmuidie\ImageRack;

use \Mockery as Mockery;
use \Symfony\Component\HttpFoundation\Request;
use \Symfony\Component\HttpFoundation\Response;

class ServerTest extends \PHPUnit_Framework_TestCase
{

    protected $source;
    protected $cache;
    protected $imageManager;

    protected function setUp()
    {
        $this->source = Mockery::mock('\League\Flysystem\FilesystemInterface');
        $this->cache = Mockery::mock('\League\Flysystem\FilesystemInterface');
        $this->imageManager = Mockery::mock('\Intervention\Image\ImageManager');
    }

    protected function tearDown()
    {
        Mockery::close();
    }

    /**
     * @covers Diarmuidie\ImageRack\Server::setTemplate
     * @covers Diarmuidie\ImageRack\Server::getTemplates
     */
    public function testTemplatesSet()
    {
        $server = new Server($this->source, $this->cache, $this->imageManager);

        $callable = function () {
            //
        };

        $server->setTemplate('template1', $callable);
        $server->setTemplate('template2', $callable);

        $actualTemplates = $server->getTemplates();

        $templates = [
            'template1' => $callable,
            'template2' => $callable,
        ];

        $this->assertEquals($templates, $actualTemplates);
    }

    /**
     * @covers Diarmuidie\ImageRack\Server::notFound
     */
    public function testDefaultNotFound()
    {
        $server = new Server($this->source, $this->cache, $this->imageManager);

        $response = $server->run();

        $this->assertEquals('File not found', $response->getContent());
        $this->assertEquals('text/html', $response->headers->get('content-type'));
        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * @covers Diarmuidie\ImageRack\Server::setNotFound
     * @covers Diarmuidie\ImageRack\Server::notFound
     */
    public function testCallableNotFound()
    {
        $server = new Server($this->source, $this->cache, $this->imageManager);

        // Setup our notFound callback to edit the response body
        $server->setNotFound(function ($response) {
            return $response->setContent('Image not found.');
        });

        $response = $server->run();

        $this->assertEquals('Image not found.', $response->getContent());
        $this->assertEquals('text/html', $response->headers->get('content-type'));
        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * @covers Diarmuidie\ImageRack\Server::send
     */
    public function testSendSendsResponse()
    {
        $server = new Server($this->source, $this->cache, $this->imageManager);

        $response = new Response('Test Content');

        $this->expectOutputString('Test Content');
        $server->send($response);
    }

    /**
     * @covers Diarmuidie\ImageRack\Server::run
     * @covers Diarmuidie\ImageRack\Server::validRequest
     */
    public function testSendNotFoundForURLWithoutTemplate()
    {
        /*
         * Setup the mock objects
         */
        $request = Mockery::mock('\Symfony\Component\HttpFoundation\Request')
        ->shouldReceive('getPathInfo')
        ->andReturn('invalidPath.png')
        ->once()
        ->mock();

        /*
         * Run the server
         */
        $server = new Server($this->source, $this->cache, $this->imageManager, $request);
        $response = $server->run();

        /*
         * Test assertions
         */
        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * @covers Diarmuidie\ImageRack\Server::run
     * @covers Diarmuidie\ImageRack\Server::validRequest
     */
    public function testSendNotFoundForURLWithInvalidTemplate()
    {
        /*
         * Setup the mock objects
         */
        $request = Mockery::mock('\Symfony\Component\HttpFoundation\Request')
        ->shouldReceive('getPathInfo')
        ->andReturn('template/image.png')
        ->once()
        ->mock();

        /*
         * Run the server
         */
        $server = new Server($this->source, $this->cache, $this->imageManager, $request);
        $response = $server->run();

        /*
         * Test assertions
         */
        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * @covers Diarmuidie\ImageRack\Server::run
     * @covers Diarmuidie\ImageRack\Server::serveFromCache
     * @covers Diarmuidie\ImageRack\Server::serveFromSource
     */
    public function testSendNotFoundForValidURL()
    {
        /*
         * Setup the mock objects
         */
        $request = Mockery::mock('\Symfony\Component\HttpFoundation\Request')
        ->shouldReceive('getPathInfo')
        ->andReturn('template/image.png')
        ->once()
        ->mock();

        $this->cache
        ->shouldReceive('has')->with('template/image.png')->andReturn(false)->once();

        $this->source
        ->shouldReceive('has')->with('image.png')->andReturn(false)->once();

        /*
         * Run the server
         */
        $server = new Server($this->source, $this->cache, $this->imageManager, $request);
        $server->setTemplate('template', function () {
            // Empty callable for testing
        });
        $response = $server->run();

        /*
         * Test assertions
         */
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testSendImageFromCache()
    {
        /*
         * Setup the mock objects
         */
        $request = Mockery::mock('\Symfony\Component\HttpFoundation\Request')
        ->shouldReceive('getPathInfo')->andReturn('template/image.png') ->once()
        ->mock();

        $modifiedTimestamp = '1435428950';

        $image = Mockery::mock('\Intervention\Image\Image')
        ->shouldReceive('getMimetype')->andReturn('image/png')->once()
        ->shouldReceive('getSize')->andReturn(1234)->once()
        ->shouldReceive('getTimestamp')->andReturn($modifiedTimestamp)->once()
        ->mock();

        $this->cache
        ->shouldReceive('has')->with('template/image.png')->andReturn(true)->once()
        ->shouldReceive('get')->with('template/image.png')->andReturn($image)->once();

        /*
         * Run the server
         */
        $server = new Server($this->source, $this->cache, $this->imageManager, $request);
        $server->setTemplate('template', function () {
            // Empty callable for testing
        });
        $response = $server->run();

        /*
         * Test assertions
         */
        $actualModified = new \DateTime();
        $actualModified->setTimestamp($modifiedTimestamp);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(1234, $response->headers->get('content-length'));
        $this->assertEquals($actualModified, $response->getLastModified());
    }

    public function testSendImageFromSource()
    {
        /*
         * Setup the mock objects
         */
        $request = Mockery::mock('\Symfony\Component\HttpFoundation\Request')
        ->shouldReceive('getPathInfo')->andReturn('template/image.png') ->once()
        ->mock();

        $imageContent = str_repeat('.', 1234);
        $image = Mockery::mock('\Intervention\Image\Image');
        $image->mime = 'image/png';
        $image->encoded = $imageContent;

        $file = Mockery::mock('\League\Flysystem\File')
        ->shouldReceive('readStream')->andReturn(Mockery::type('resource'))->once()
        ->mock();

        $template = Mockery::mock('\Diarmuidie\ImageRack\Image\TemplateInterface')
        ->shouldReceive('process')->andReturn($image)->once()
        ->mock();

        $this->cache
        ->shouldReceive('has')->with('template/image.png')->andReturn(false)->once()
        ->shouldReceive('write')->once();

        $this->source
        ->shouldReceive('has')->with('image.png')->andReturn(true)->once()
        ->shouldReceive('get')->with('image.png')->andReturn($file)->once();

        $this->imageManager
        ->shouldReceive('make')->andReturn($image)->once();

        /*
         * Run the server
         */
        $server = new Server($this->source, $this->cache, $this->imageManager, $request);
        $server->setTemplate('template', function () use ($template) {
            return $template;
        });
        $response = $server->run();

        /*
         * Test assertions
         */
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(1234, $response->headers->get('content-length'));
        $this->assertEquals('image/png', $response->headers->get('content-type'));
        $this->assertEquals($imageContent, $response->getContent());

    }
}
