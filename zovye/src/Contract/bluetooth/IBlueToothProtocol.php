<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\Contract\bluetooth;

interface IBlueToothProtocol
{
    const BASE64 = '\base64_encode';
    const HEX = '\bin2hex';

    /**
     * 返回协议的中文名称
     * @return string
     */
    function getTitle(): string;

    /**
     * 把后台设置的设备BUID转化为协议需要的UID
     */
    function transUID($uid);

    /**
     * 蓝牙设备已连接
     * @param $device_id
     * @param mixed $data
     * @return ICmd
     */
    function onConnected($device_id, $data = ''): ?ICmd;

    /**
     * 初始化蓝牙设备
     * @param $device_id
     * @return mixed
     */
    function initialize($device_id);

    /**
     * 蓝牙设备出货
     * @param $device_id
     * @param $data
     * @return ICmd
     */
    function open($device_id, $data): ?ICmd;

    /**
     * 处理蓝牙设备返回的消息
     * @param  $device_id
     * @param $data
     * @return IResponse
     */
    function parseResponse($device_id, $data): ?IResponse;
}