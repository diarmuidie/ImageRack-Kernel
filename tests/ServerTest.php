<?php

namespace Diarmuidie\ImageRack;

use \Mockery as Mockery;
use \Pimple\Container;

class ServerTest extends \PHPUnit_Framework_TestCase
{

    protected $container;

    protected function setUp()
    {
        $this->container = new Container();
        $this->container['request'] = function ($c) {
            return new Http\Request('');
        };
        $this->container['response'] = function ($c) {
            return new Http\Response();
        };
    }

    protected function tearDown()
    {
        Mockery::close();
    }

    public function testTemplatesSet()
    {
        $server = new Server($this->container);

        $templates = array('template1', 'template2');

        $server->setTemplates($templates);

        $serverReflection = new \ReflectionProperty($server, 'templates');
        $serverReflection->setAccessible(true);
        $actualTemplates = $serverReflection->getValue($server);

        $this->assertEquals($templates, $actualTemplates);
    }

    public function testNotFoundCallableRun()
    {
        $server = new Server($this->container);

        // Setup our notFound callback to edit the response body
        $server->notFound(function ($response) {
            $response->setBody('Callback Called');
        });

        $response = $server->run();

        $actualBody = $response->getBody();

        $this->assertEquals('Callback Called', $actualBody);
    }

    public function testNotFound404HeaderSet()
    {
        $server = new Server($this->container);

        $response = $server->run();

        $actualStatus = $response->getStatusCode();

        $this->assertEquals(404, $actualStatus);
    }

}
