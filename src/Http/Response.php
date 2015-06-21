<?php

namespace Diarmuidie\ImageRack\Http;

use \League\Flysystem\File;

class Response
{
    private $body;

    private $headers = array();

    private $status;

    public function setBody($body)
    {
        $this->body = $body;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function setHeader($header)
    {
        $this->headers[] = $header;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function setStatusCode($code)
    {
        $this->status = $code;
    }

    public function getStatusCode()
    {
        return $this->status;
    }

    public function setContentType($contentType)
    {
        $this->setHeader('Content-Type: ' . $contentType);
    }

    public function setContentLength($contentLength)
    {
        $this->setHeader('Content-Length: ' . $contentLength);
    }

    public function setLastModified(\Datetime $lastModified)
    {
        $this->setHeader('Last-Modified: ' . $lastModified->format('D, d M Y H:i:s \G\M\T'));
    }

    public function sendHeaders()
    {
        foreach ($this->headers as $header) {
            header($header);
        }
        if (is_int($this->status)) {
            http_response_code($this->status);
        }
    }

    /**
     * Stream a streamable resource to the browser
     *
     * @param  resource $stream The streamable resource
     * @return null
     */
    public function stream($stream)
    {
        $this->sendHeaders();
        fpassthru($stream);
    }

    /**
     * Send the body to the browser
     *
     * @return null
     */
    public function send()
    {
        $this->sendHeaders();
        echo $this->body;
    }
}
