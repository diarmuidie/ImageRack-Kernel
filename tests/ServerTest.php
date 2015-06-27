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
     * @covers Diarmuidie\ImageRack\Server::notFound
     */
    public function testCallableNotFound()
    {
        $server = new Server($this->source, $this->cache, $this->imageManager);

        // Setup our notFound callback to edit the response body
        $server->notFound(function ($response) {
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
     */
    public function testSendNotFoundForBadURL()
    {
        $request = Mockery::mock('\Symfony\Component\HttpFoundation\Request')
        ->shouldReceive('getPathInfo')
        ->andReturn('invalidPath.png')
        ->once()
        ->mock();

        $server = new Server($this->source, $this->cache, $this->imageManager, $request);
        $response = $server->run();

        $this->assertEquals(404, $response->getStatusCode());
    }


    public function testSendImageFromCache()
    {
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

        $server = new Server($this->source, $this->cache, $this->imageManager, $request);

        $server->setTemplate('template', function () {
            //
        });

        $response = $server->run();

        $actualModified = new \DateTime();
        $actualModified->setTimestamp($modifiedTimestamp);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(1234, $response->headers->get('content-length'));
        $this->assertEquals($actualModified, $response->getLastModified());
    }
}
