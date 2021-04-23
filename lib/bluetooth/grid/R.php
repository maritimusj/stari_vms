<?php

namespace bluetooth\grid;

class R extends cmd
{
    function getID()
    {
        return "R";
    }

    function getRaw()
    {
        return "+R?\r\n";
    }

    function getMessage()
    {
        return "=> 请求密钥";
    }
}