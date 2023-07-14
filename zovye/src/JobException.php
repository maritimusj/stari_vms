<?php

namespace zovye;

use RuntimeException;

class JobException extends RuntimeException
{

    private $data;

    public function __construct($message, $data)
    {
        parent::__construct($message);
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }
}