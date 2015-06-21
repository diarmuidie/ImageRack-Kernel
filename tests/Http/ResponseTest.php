<?php

namespace Diarmuidie\ImageRack\Http;

class ResponseTest extends \PHPUnit_Framework_TestCase
{
    protected $response;

    protected function setUp()
    {
        $this->response = new Response();
    }

    public function testHeadersSet()
    {
        $this->response->setHeader('XTestHeader: Header1');
        $this->response->setHeader('XTestHeader: HEader2');

        $expectedHeaders = array(
            'XTestHeader: Header1',
            'XTestHeader: HEader2'
        );

        $actualHeaders = $this->response->getHeaders();

        $this->assertEquals($expectedHeaders, $actualHeaders);
    }

    public function testStatusCodeSet()
    {
        $this->response->setStatusCode(404);

        $actualStatus = $this->response->getStatusCode();

        $this->assertEquals(404, $actualStatus);
    }

    public function testSetContentTypeHeader()
    {
        $this->response->setContentType('image/png');

        $expectedHeaders = array('Content-Type: image/png');

        $actualHeaders = $this->response->getHeaders();

        $this->assertEquals($expectedHeaders, $actualHeaders);
    }

    public function testSetContentLengthHeader()
    {
        $this->response->setContentLength(12345);

        $expectedHeaders = array('Content-Length: 12345');

        $actualHeaders = $this->response->getHeaders();

        $this->assertEquals($expectedHeaders, $actualHeaders);
    }

    public function testSetLastModifiedHeader()
    {
        $timestamp = new \Datetime('10/Jun/2015:13:55:36 -0000');
        $this->response->setLastModified($timestamp);

        $expectedHeaders = array('Last-Modified: Wed, 10 Jun 2015 13:55:36 GMT');

        $actualHeaders = $this->response->getHeaders();

        $this->assertEquals($expectedHeaders, $actualHeaders);
    }

    public function testBodySet()
    {
        $this->response->setBody('Test Body');

        $actualBody = $this->response->getBody();

        $this->assertEquals('Test Body', $actualBody);
    }

    public function testBodySent()
    {
        $this->response->setBody('Test Body');
        $this->response->send();

        $this->expectOutputString('Test Body');
    }


}
