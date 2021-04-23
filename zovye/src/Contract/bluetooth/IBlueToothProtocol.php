<?php


namespace zovye\Contract\bluetooth;

interface IBlueToothProtocol
{
    const BASE64 = '\base64_encode';
    const HEX = '\bin2hex';
    
    function transUID($uid);

    /**
     * @param $device_id
     * @param string $data
     * @return ICmd
     */
    function onConnected($device_id, $data = ''): ?ICmd;


    function initialize($device_id);

    /**
     * @param $device_id
     * @param $data
     * @return ICmd
     */
    function open($device_id, $data): ?ICmd;

    /**
     * @param  $device_id
     * @param $data
     * @return IResult
     */
    function parseMessage($device_id, $data): ?IResult;

    function getTitle(): string;
}