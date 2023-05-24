<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace bluetooth\grid;

class R extends cmd
{
    function getID(): string
    {
        return "R";
    }

    function getRaw(): string
    {
        return "+R?\r\n";
    }

    function getMessage(): string
    {
        return "=> 请求密钥";
    }
}