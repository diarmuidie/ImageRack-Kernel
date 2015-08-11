<?php

namespace Diarmuidie\ImageRack;

use \League\Flysystem\File;
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
     * @var callable
     */
    private $error;

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
     * The cache max age in seconds (one month)
     * @var integer
     */
    private $maxAge = 2678400;

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

        $this->response = new Response();

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
     * Set the Cache header max age. Set to zero to disable
     *
     * @param  Integer                  $maxAge Amount of seconds to cache the image
     * @return Void
     * @throws InvalidArgumentException
     */
    public function setHttpCacheMaxAge($maxAge)
    {
        if (!is_int(($maxAge))) {
            throw new \InvalidArgumentException(
                'setHttpCacheMaxAge method only accepts integers. Input was: ' . $maxAge
            );
        }
        $this->maxAge = $maxAge;
    }

    /**
     * Get the the Cache header max age.
     *
     * @return Int Cache age in seconds.
     */
    public function getHttpCacheMaxAge()
    {
        return $this->maxAge;
    }

    /**
     * Run the image Server on the curent request
     *
     * @return Response The response object
     */
    public function run()
    {
        // Catch all errors and convert to exceptions
        set_error_handler(array('\Diarmuidie\ImageRack\Server', 'handleErrors'));

        // Catch all uncaught exceptions
        set_exception_handler(array($this, 'error'));

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
     * Convert errors into ErrorException objects
     *
     * This method catches PHP errors and converts them into \ErrorException objects.
     *
     * @param  int            $errno   The numeric type of the Error
     * @param  string         $errstr  The error message
     * @param  string         $errfile The absolute path to the affected file
     * @param  int            $errline The line number of the error in the affected file
     * @return bool
     * @throws \ErrorException
     */
    public static function handleErrors($errno, $errstr = '', $errfile = '', $errline = '')
    {
        if (!($errno & error_reporting())) {
            return;
        }
        throw new \ErrorException($errstr, $errno, 0, $errfile, $errline);
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

            $this->setHttpCacheHeaders(
                $lastModified,
                md5($this->getCachePath() . $lastModified->getTimestamp()),
                $this->maxAge
            );

            // Respond with 304 not modified
            if ($this->response->isNotModified($this->request)) {
                return true;
            }

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

            // Set the headers
            $this->response->headers->set('Content-Type', $image->mime);
            $this->response->headers->set('Content-Length', strlen($image->encoded));

            $lastModified = new \DateTime(); // now

            $this->setHttpCacheHeaders(
                $lastModified,
                md5($this->getCachePath() . $lastModified->getTimestamp()),
                $this->maxAge
            );

            // Send the processed image in the response
            $this->response->setContent($image->encoded);

            // Write the processed image to the cache
            $this->cache->write($this->getCachePath(), $image->encoded);

            return true;
        }
        return false;
    }

    /**
     * Set the appripriate HTTP cache headers
     *
     * @param DateTime $lastModified The last time the resource was modified.
     * @param string   $eTag         Unique eTag for the resource.
     * @param integer  $maxAge       The max age (in seconds).
     * @return void
     */
    private function setHttpCacheHeaders(\DateTime $lastModified, $eTag, $maxAge)
    {
        $this->response->setMaxAge($maxAge);
        $this->response->setPublic();

        if ($this->maxAge === 0) {
            $this->response->headers->set('Cache-Control', 'no-cache');
            return;
        }

        $this->response->setLastModified($lastModified);
        $this->response->setEtag($eTag);
    }

    /**
     * Set a user defined not found callback
     *
     * @param  callable $callable The user defined notFound callback
     * @return null
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
    protected function notFound()
    {
        // Set the default not found response
        $this->response->setContent('File not found');
        $this->response->headers->set('content-type', 'text/html');
        $this->response->setStatusCode(Response::HTTP_NOT_FOUND);

        // Execute the user defined notFound callback
        if (is_callable($this->notFound)) {
            $this->response = call_user_func($this->notFound, $this->response);
        }
    }

    /**
     * Set a user defined error callback
     *
     * @param  callable $callable The user defined error callback
     * @return null
     */
    public function setError(callable $callable)
    {
        $this->error = $callable;
    }

    /**
     * Set an error response
     *
     * @param  Exceptions $exception The caught exception
     * @return null
     */
    public function error($exception)
    {
        // Set the default error response
        $this->response->setContent('There has been a problem serving this request.');
        $this->response->headers->set('content-type', 'text/html');
        $this->response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);

        // Execute the user defined error callback
        if (is_callable($this->error)) {
            $this->response = call_user_func($this->error, $this->response, $exception);
        }

        // Send the response
        $this->response->send();
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
     * @param  File              $file         The file handler for the image
     * @param  ImageManager      $imageManager The image manipulation manager
     * @param  TemplateInterface $template     The template
     * @return Image                           The processed image
     */
    private function processImage(File $file, ImageManager $imageManager, TemplateInterface $template)
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
