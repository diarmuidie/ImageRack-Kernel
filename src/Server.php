<?php

namespace Diarmuidie\ImageRack;

use Pimple\Container as Container;
use Dflydev\Canal\Analyzer\Analyzer;

/**
 *
 */
class Server
{
    /**
     * DI Container
     * @var Pimple\Container
     */
    private $container;

    /**
     * The request object
     * @var Diarmuidie\Http\Request
     */
    private $req;

    /**
     * The response object
     * @var Diarmuidie\Http\Response
     */
    private $res;

    /**
     * Array of valid template names
     * @var array
     */
    private $templates = array();

    /**
     * Pass in the DI container and set the request/response objects
     *
     * @param Container $container The DI Container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->req = $this->container['request'];
        $this->res = $this->container['response'];
    }

    /**
     * Set an array of allowed template names
     *
     * @param Array $templates
     */
    public function setTemplates(Array $templates)
    {
        $this->templates = $templates;
    }

    /**
     * Run the image Server
     *
     * @return Diarmuidie\Http\Response The response object
     */
    public function run()
    {
        // Send a not found response if the request is not valid
        if (!$this->validRequest()) {
            $this->res->notFound();
            return $this->res;
        }

        $cache = $this->container['cache'];
        $cachePath = $this->req->getTemplate() . '/' . $this->req->getPath();

        // First try and load the image from the cache
        if ($cache->has($cachePath)) {
            $file = $cache->get($cachePath);
            $this->res->sendFile($file);
            return $this->res;
        }

        $source = $this->container['source'];
        $sourcePath = $this->req->getPath();

        // Secondly try load the source image
        if ($source->has($sourcePath)) {
            $file = $source->get($sourcePath);

            // Process the image using the template
            $image = new \Diarmuidie\ImageRack\Image\Process(
                $file->readStream(),
                $this->container['imageManager']
            );

            // Get the template object
            $template = $this->templates[$this->req->getTemplate()]();

            // Process the image
            $image = $image->process($template);

            // Send the processed image in the response
            $this->res->setBody($image->encoded);

            // Manually set the headers
            $this->res->setContentType($image->mime);
            $this->res->setContentLength(strlen($image->encoded));

            // Send the response
            $this->res->send();

            // Write the processed image to the cache
            $cache->write($cachePath, $image->encoded);

            return $this->res;
        }

        // Finally return a not found response
        $this->res->notFound();
        return $this->res;
    }

    /**
     * Test if a request is valid
     *
     * @return Boolean
     */
    private function validRequest()
    {
        // Is a path and template set
        if (empty($this->req->getTemplate()) or empty($this->req->getPath())) {
            return false;
        }

        // Is the template a valid template
        if (!array_key_exists($this->req->getTemplate(), $this->templates)) {
            return false;
        }
        return true;
    }

}
