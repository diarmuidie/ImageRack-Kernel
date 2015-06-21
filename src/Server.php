<?php

namespace Diarmuidie\ImageRack;

use \Pimple\Container;
use \League\Flysystem\Handler;
use \Intervention\Image\ImageManager;
use \Intervention\Image\Image;
use \Diarmuidie\ImageRack\Image\TemplateInterface;

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
     * The response object
     * @var
     */
    private $notFound;

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
            $this->notFound();
            return $this->res;
        }

        $cache = $this->container['cache'];
        $cachePath = $this->req->getTemplate() . '/' . $this->req->getPath();

        // First try and load the image from the cache
        if ($cache->has($cachePath)) {
            $file = $cache->get($cachePath);
            $this->sendCacheImage($file);
            return $this->res;
        }

        $source = $this->container['source'];
        $sourcePath = $this->req->getPath();

        // Secondly try load the source image
        if ($source->has($sourcePath)) {
            $file = $source->get($sourcePath);

            // Get the template object
            $template = $this->templates[$this->req->getTemplate()]();

            // Process the image
            $image = $this->processImage(
                $file,
                $this->container['imageManager'],
                $template
            );

            $this->sendProcessedImage($image);

            // Write the processed image to the cache
            $cache->write($cachePath, $image->encoded);

            return $this->res;
        }

        // Finally return a not found response
        $this->notFound();
        return $this->res;
    }

    private function sendCacheImage(Handler $file)
    {
        // Set the headers
        $this->res->setContentType($file->getMimetype());
        $this->res->setContentLength($file->getSize());
        $lastModified = new \DateTime();
        $lastModified->setTimestamp($file->getTimestamp());
        $this->res->setLastModified($lastModified);

        // Stream the response
        $this->res->stream($file->readStream());
    }

    private function processImage(Handler $file, ImageManager $imageManager, TemplateInterface $template)
    {
        // Process the image using the template
        $image = new \Diarmuidie\ImageRack\Image\Process(
            $file->readStream(),
            $imageManager
        );

        // Process the image
        return $image->process($template);
    }

    private function sendProcessedImage(Image $image)
    {
        // Send the processed image in the response
        $this->res->setBody($image->encoded);

        // Manually set the headers
        $this->res->setContentType($image->mime);
        $this->res->setContentLength(strlen($image->encoded));
        $lastModified = new \DateTime(); // now
        $this->res->setLastModified($lastModified);

        // Send the response
        $this->res->send();
    }

    public function notFound($callable = null)
    {
        if (is_callable($callable)) {
            $this->notFound = $callable;
        } else {
            $this->res->setStatusCode(404);
            if (is_callable($this->notFound)) {
                call_user_func($this->notFound, $this->res);
            }
            $this->res->send();
        }
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
