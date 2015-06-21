<?php

namespace Diarmuidie\ImageRack\Http;

class RequestTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider URIProvider
     */
    public function testParsesTemplate($uri, $template, $path)
    {
        $request = new Request($uri);

        $this->assertEquals($template, $request->getTemplate());
    }

    /**
     * @dataProvider URIProvider
     */
    public function testParsesPath($uri, $template, $path)
    {
        $request = new Request($uri);

        $this->assertEquals($path, $request->getPath());
    }

    public function URIProvider()
    {
        return array(
            array(
                '/template/folder/image.png',
                'template',
                'folder/image.png'
            ),
            // No leading slash
            array(
                'template/folder/image.png',
                'template',
                'folder/image.png'
            ),
            // No template
            array(
                '/image.png',
                null,
                'image.png'
            ),
            // No path
            array(
                "/template/",
                'template',
                null
            ),
            // No path, no template
            array(
                "/",
                null,
                null
            ),
            // fully qualified URL
            array(
                'http://example.com/template/folder/image.png',
                'template',
                'folder/image.png'
            ),
        );
    }
}
