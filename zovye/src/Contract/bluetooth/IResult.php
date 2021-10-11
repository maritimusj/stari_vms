<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
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