<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\Contract\bluetooth;

interface IResult
{
    function isValid();

    function isOpenResultOk();

    function isOpenResultFail();

    function isReady();

    function getBatteryValue();

    function getCode();

    function getMessage();

    function getDeviceID();

    function getSerial();

    function getRawData();

    function getCmd();
    
    function getPayloadData();
}