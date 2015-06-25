<?php

namespace Diarmuidie\ImageRack;

use \Pimple\Container;
use \League\Flysystem\Handler;
use \League\Flysystem\FilesystemInterface;
use \Intervention\Image\ImageManager;
use \Intervention\Image\Image;
use \Diarmuidie\ImageRack\Image\TemplateInterface;
use \Symfony\Component\HttpFoundation\Request;
use \Symfony\Component\HttpFoundation\Response;

/**
 *
 */
class Server
{


    private $source;
    private $cache;
    private $imageManager;
    private $request;

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

    private $template;
    private $path;

    /**
     * Pass in the DI container and set the request/response objects
     *
     * @param Container $container The DI Container
     */
    public function __construct(
        FilesystemInterface $source,
        FilesystemInterface $cache,
        ImageManager $imageManager,
        Request $request = null
    ) {
        $this->source = $source;
        $this->cache = $cache;
        $this->imageManager = $imageManager;

        if (!$request) {
            $request = Request::createFromGlobals();
        }
        $this->request = $request;

        $this->parsePath($request->getPathInfo());
    }

    /**
     * Set an array of allowed template names
     *
     * @param Array $templates
     */
    public function setTemplate($name, callable $templateCallback)
    {
        $this->templates[$name] = $templateCallback;
    }


    /**
     * Set an array of allowed template names
     *
     * @param Array $templates
     */
    public function getTemplates()
    {
        return $this->templates;
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
            $this->response = new Response();
            $this->notFound();
            return $response;
        }

        $cachePath = $request->getTemplate() . '/' . $request->getPath();

        // First try and load the image from the cache
        if ($this->cache->has($cachePath)) {
            $file = $this->cache->get($cachePath);
            $this->sendCacheImage($file);
            return $response;
        }

        $sourcePath = $this->req->getPath();

        // Secondly try load the source image
        if ($this->source->has($sourcePath)) {
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

            return $response;
        }

        // Finally return a not found response
        return $response->setStatusCode(Response::HTTP_NOT_FOUND);
    }

    private function sendCacheImage(Handler $file, $response)
    {
        // Set the headers
        $response->headers->set('Content Type', $file->getMimetype());
        $response->headers->set('Content Length', $file->getSize());
        $lastModified = new \DateTime();
        $lastModified->setTimestamp($file->getTimestamp());
        $response->setLastModified($lastModified);

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
        // If a callable is provided then set the notFound callback
        if (is_callable($callable)) {
            $this->notFound = $callable;
            return true;
        }

        // Set the default not found response
        $this->response->setContent('File not found');
        $this->response->headers->set(array('content-type' => 'text/html'));
        $this->response->setStatusCode(Response::HTTP_NOT_FOUND);

        // Execute the user defined notFound callback
        if (is_callable($this->notFound)) {
            $this->response = call_user_func($this->notFound, $this->response);
        }
    }

    private function parsePath($path)
    {
        // strip out any query params
        if (preg_match('/^\/?(.*?)\/(.*)/', $path, $matches)) {
            $this->template = $matches[1];
            $this->path = $matches[2];
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
        if (empty($this->template) or empty($this->path)) {
            return false;
        }

        // Is the template a valid template
        if (!array_key_exists($this->template, $this->templates)) {
            return false;
        }
        return true;
    }
}
