<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\bn;

use zovye\Contract\bluetooth\ICmd;
use zovye\Contract\bluetooth\IResponse;

class response implements IResponse
{

    function getID()
    {
        // TODO: Implement getID() method.
    }

    function isOpenResultOk()
    {
        // TODO: Implement isOpenResultOk() method.
    }

    function isOpenResultFail()
    {
        // TODO: Implement isOpenResultFail() method.
    }

    function getErrorCode()
    {
        // TODO: Implement getErrorCode() method.
    }

    function isReady()
    {
        // TODO: Implement isReady() method.
    }

    function getBatteryValue()
    {
        // TODO: Implement getBatteryValue() method.
    }

    function getMessage()
    {
        // TODO: Implement getMessage() method.
    }

    function getSerial()
    {
        // TODO: Implement getSerial() method.
    }

    function getRawData()
    {
        // TODO: Implement getRawData() method.
    }

    function getCmd(): ?ICmd
    {
        // TODO: Implement getCmd() method.
    }
}