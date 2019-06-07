<?php

namespace Bramus\Router;

class Response
{
    private $result;

    public function __construct($result)
    {
        $this->result = $result;
    }

    public function handle()
    {
        $result = $this->result;
        echo $result;
        return true;
    }
}
