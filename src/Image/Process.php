<?php

namespace Diarmuidie\ImageRack\Image;

use \Diarmuidie\ImageRack\Image\TemplateInterface;
use \Intervention\Image\ImageManager;

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
    public function __construct($image, ImageManager $imageManager)
    {
        $this->image = $image;
        $this->imageManager = $imageManager;
    }

    /**
     * Run the named template against the image
     * @param  String $template          The template to run
     * @return Intervention\Image\Image  The processed image
     */
    public function process(TemplateInterface $template)
    {
        // Create a new intervention image object
        $image = $this->imageManager->make($this->image);

        $image = $template->process($image);

        // Encode the image if it hasn't
        // been encoded in the template.
        if (!$image->isEncoded()) {
            $image->encode();
        }

        return $image;
    }
}
