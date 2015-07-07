<?php

namespace Diarmuidie\ImageRack;

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

    /**
     * @var \League\Flysystem\FilesystemInterface
     */
    private $source;

    /**
     * @var \League\Flysystem\FilesystemInterface
     */
    private $cache;

    /**
     * @var \Intervention\Image\ImageManager
     */
    private $imageManager;

    /**
     * @var \Symfony\Component\HttpFoundation\Request
     */
    private $request;

    /**
     * @var callable
     */
    private $notFound;

    /**
     * Array of valid templates [name => callable]
     * @var array
     */
    private $templates = array();

    /**
     * Current request template
     * @var string
     */
    private $template;

    /**
     * Current request path
     * @var string
     */
    private $path;

    /**
     * Bootstrap the server dependencies
     *
     * @param FilesystemInterface $source       The source image location
     * @param FilesystemInterface $cache        The cache image location
     * @param ImageManager        $imageManager The image manipulatpion object
     * @param Request             $request      Optional overwride request object
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

        // If no request is provided create one using the server globals
        if (!$request) {
            $request = Request::createFromGlobals();
        }
        $this->request = $request;

        // Populate the $path and $template properties
        $this->parsePath($request->getPathInfo());
    }

    /**
     *  Set an allowed template and callable to return a TemplateInterface
     *
     * @param string   $name             The name of the template
     * @param callable $templateCallback The template callback
     */
    public function setTemplate($name, callable $templateCallback)
    {
        $this->templates[$name] = $templateCallback;
    }


    /**
     * Get an array of templates
     *
     * @return Array The array of set templates
     */
    public function getTemplates()
    {
        return $this->templates;
    }

    /**
     * Run the image Server on the curent request
     *
     * @return Response The response object
     */
    public function run()
    {
        // Send a not found response if the request is not valid
        if (!$this->validRequest()) {
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
        $this->notFound();
        return $this->response;
    }

    /**
     * Generate a response object for a cached image
     *
     * @param  string $path Path to the cached image
     * @return Boolean
     */
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

    /**
     * Process a source image and Generate a response object
     *
     * @param  string $path Path to the source image
     * @return Boolean
     */
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

            // Set the headers
            $this->response->headers->set('Content-Type', $image->mime);
            $this->response->headers->set('Content-Length', strlen($image->encoded));
            $lastModified = new \DateTime(); // now
            $this->response->setLastModified($lastModified);

            // Send the processed image in the response
            $this->response->setContent($image->encoded);

            // Write the processed image to the cache
            $this->cache->write($this->getCachePath(), $image->encoded);

            return true;
        }
        return false;
    }

    /**
     * Set a user defined not found callback
     *
     * @param  callable $callable The user defined notFound callback
     * @return Boolean
     */
    public function setNotFound(callable $callable)
    {
        $this->notFound = $callable;
    }

    /**
     * Set a not found response
     *
     * @return null
     */
    public function notFound()
    {
        // Set the default not found response
        $this->response = new Response();
        $this->response->setContent('File not found');
        $this->response->headers->set('content-type', 'text/html');
        $this->response->setStatusCode(Response::HTTP_NOT_FOUND);

        // Execute the user defined notFound callback
        if (is_callable($this->notFound)) {
            $this->response = call_user_func($this->notFound, $this->response);
        }
    }

    /**
     * Send the response to the browser
     *
     * @param  Response $response Optional overwrite response
     */
    public function send(Response $response = null)
    {
        if ($response) {
            $this->response = $response;
        }
        $this->response->prepare($this->request);
        $this->response->send();
    }

    /**
     * Process an image using the provided template
     *
     * @param  Handler           $file         The handler for the image file
     * @param  ImageManager      $imageManager The image manipulation manager
     * @param  TemplateInterface $template     The template
     * @return Image                           The processed image
     */
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

    /**
     * Get the path for the cached file
     *
     * @return string
     */
    private function getCachePath()
    {
        return $this->template . '/' . $this->path;
    }

    /**
     * Get the path for the source file
     *
     * @return string
     */
    private function getSourcePath()
    {
        return $this->path;
    }

    /**
     * Parse the request path to extract the path and template elements
     *
     * @param  string $path The complet server path
     */
    private function parsePath($path)
    {
        // strip out any query params
        if (preg_match('/^\/?(.*?)\/(.*)/', $path, $matches)) {
            $this->template = $matches[1];
            $this->path = $matches[2];
        }
    }

    /**
     * Test if the request is valid
     *
     * @return Boolean
     */
    private function validRequest()
    {
        // Is a path and template set
        if (empty($this->template) || empty($this->path)) {
            return false;
        }

        // Is the template a valid template
        if (!array_key_exists($this->template, $this->templates)) {
            return false;
        }
        return true;
    }
}
