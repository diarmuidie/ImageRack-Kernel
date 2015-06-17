<?php

namespace Diarmuidie\ImageRack\Image;

use League\Flysystem\File;
use Diarmuidie\ImageRack\Image\TemplateInterface;
use Templates;
use Intervention\Image\ImageManagerStatic as Image;

/**
 * Object to process an image
 */
class Process
{
    /**
     * The image
     * @var resource
     */
    private $image;

    /**
     * The image manager
     * @var Intervention\Image\ImageManager
     */
    private $imageManager;

    /**
     * Set the streamable image resource on startup
     *
     * @param resource $image The image resource
     */
    public function __construct($image, \Intervention\Image\ImageManager $imageManager)
    {
        $this->image = $image;
        $this->imageManager = $imageManager;
    }

    /**
     * Run the named template against the image
     * @param  String $template          The template to run
     * @return Intervention\Image\Image  The processed image
     */
    public function process($template)
    {
        // Create a new intervention image object
        $image = $this->imageManager->make($this->image);

        // Create a tempalte object
        $templateName = "\Templates\\" . ucfirst($template);

        $templateHandler = new $templateName();

        $image = $templateHandler->process($image);
        $image = $image->encode();

        return $image;
    }
}
