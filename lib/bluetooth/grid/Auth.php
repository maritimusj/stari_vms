<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace bluetooth\grid;

class Auth extends cmd
{
    private $password;

    public function __construct($device_id, $password)
    {
        parent::__construct($device_id);
        $this->password = strval($password);
    }

    function getID()
    {
        return "AUTH";
    }

    function getRaw()
    {
        return "+PK:{$this->password}\r\n";
    }

    function getMessage()
    {
        return "=> 请求授权";
    }
}