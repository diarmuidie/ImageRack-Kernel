<?php

namespace Diarmuidie\ImageRack\Http;

class Request
{
    private $path;

    private $template;

    public function __construct($path)
    {
        $this->parsePath($path);
    }

    private function parsePath($path)
    {
        // strip out any query params
        $path = parse_url($path, PHP_URL_PATH);
        if (preg_match('/^\/?(.*?)\/(.*)/', $path, $matches)) {
            $this->template = $matches[1];
            $this->path = $matches[2];
        }
    }

    public function validPath()
    {
        if (empty($this->getTemplate()) or empty($this->getPath())) {
            return false;
        }
        return true;
    }

    public function getTemplate()
    {
        return $this->template;
    }

    public function getPath()
    {
        return $this->path;
    }
}
