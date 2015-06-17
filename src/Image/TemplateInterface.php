<?php

namespace Diarmuidie\ImageRack\Image;

interface TemplateInterface
{
    /**
     * Take an Intervention image object, do a number of conversions
     * on it (resize, colerise, crop etc.) and return the object.
     *
     * @param  InterventionImageImage $image The input image
     * @return InterventionImageImage        The processed image
     */
    public function process(\Intervention\Image\Image $image);
}
