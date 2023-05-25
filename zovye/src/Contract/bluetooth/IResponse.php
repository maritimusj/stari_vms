<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\Contract\bluetooth;

interface IResponse
{
    /**
     * 返回结果ID
     */
    function getID();

    /**
     * 是否为出货成功
     */
    function isOpenResultOk();

    /**
     * 是否为出货失败
     */
    function isOpenResultFail();

    /**
     * 返回错误代码
     */
    function getErrorCode();

    /**
     * 设备是否已经准备好出货
     */
    function isReady();

    /**
     * 获取电量值，0-100；不支持或者不包含电量，返回-1
     */
    function getBatteryValue();

    /**
     * 返回描述文本
     */
    function getMessage();

    /**
     * 获取序号，不支持或者没有返回空
     */
    function getSerial();

    /**
     * 获取原始数据
     */
    function getRawData();

    /**
     * 获取附加的命令，没有返回null
     */
    function getCmd(): ?ICmd;
}