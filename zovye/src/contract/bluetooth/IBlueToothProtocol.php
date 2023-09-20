<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\contract\bluetooth;

interface IBlueToothProtocol
{
    /**
     * 返回协议的功能是否支持
     */
    function support($fn);

    /**
     * 返回协议的中文名称
     */
    function getTitle(): string;

    /**
     * 把后台设置的设备BUID转化为协议需要的UID
     */
    function transUID($uid);

    /**
     * 蓝牙设备已连接
     */
    function onConnected($device_id, $data = ''): ?ICmd;

    /**
     * 初始化蓝牙设备
     */
    function initialize($device_id);

    /**
     * 蓝牙设备出货
     */
    function open($device_id, $data): ?ICmd;

    /**
     * 处理蓝牙设备返回的消息
     */
    function parseResponse($device_id, $data): ?IResponse;
}