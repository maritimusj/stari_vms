<?php

namespace zovye;

use RuntimeException;

class JobException extends RuntimeException
{

    private  $data = null;

    public function __construct($message, $data)
    {
        parent::__construct($message, 0, 0);
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }
}