<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

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