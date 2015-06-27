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
use \Symfony\Component\HttpFoundation\StreamedResponse;

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
     * @return \Symfony\Component\HttpFoundation\Response The response object
     */
    public function run()
    {

        // Send a not found response if the request is not valid
        if (!$this->validRequest()) {
            $this->response = new Response();
            $this->notFound();
            return $this->response;
        }

        // First try and load the image from the cache
        $cachePath = $this->getCachePath();
        if ($this->serveFromCache($cachePath)) {
            return $this->response;
        }

        // Secondly try load the source image
        $sourcePath = $this->getSourcePath();
        if ($this->serveFromSource($sourcePath)) {
            return $this->response;
        }

        // Finally default to returning a not found response
        $this->response = new Response();
        $this->notFound();
        return $this->response;
    }

    private function serveFromCache($path)
    {
        //try and load the image from the cache
        if ($this->cache->has($path)) {
            $file = $this->cache->get($path);

            $this->response = new StreamedResponse();

            // Set the headers
            $this->response->headers->set('Content-Type', $file->getMimetype());
            $this->response->headers->set('Content-Length', $file->getSize());
            $lastModified = new \DateTime();
            $lastModified->setTimestamp($file->getTimestamp());
            $this->response->setLastModified($lastModified);

            $this->response->setCallback(function () use ($file) {
                fpassthru($file->readStream());
            });

            return true;
        }
        return false;
    }

    private function serveFromSource($path)
    {
        //try and load the image from the source
        if ($this->source->has($path)) {
            $file = $this->source->get($path);

            // Get the template object
            $template = $this->templates[$this->template]();

            // Process the image
            $image = $this->processImage(
                $file,
                $this->imageManager,
                $template
            );

            $this->response = new Response();

            $this->response->headers->set('Content-Type', $image->mime);
            $this->response->headers->set('Content-Length', strlen($image->encoded));
            $lastModified = new \DateTime(); // now
            $this->response->setLastModified($lastModified);

            // Send the processed image in the response
            $this->response->setContent($image->encoded);

            // Write the processed image to the cache
            $this->cache->write($cachePath, $image->encoded);

            return true;
        }
        return false;
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
        $this->response->headers->set('content-type', 'text/html');
        $this->response->setStatusCode(Response::HTTP_NOT_FOUND);

        // Execute the user defined notFound callback
        if (is_callable($this->notFound)) {
            $this->response = call_user_func($this->notFound, $this->response);
        }
    }

    public function send(Response $response = null)
    {
        if ($response) {
            $this->response = $response;
        }
        $this->response->prepare($this->request);
        $this->response->send();
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

    private function getCachePath()
    {
        return $this->template . '/' . $this->path;
    }

    private function getSourcePath()
    {
        return $this->path;
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
