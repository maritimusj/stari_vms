<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\contract\bluetooth;

interface IResponse
{
    /**
     * 返回结果ID
     */
    function getID();

    /**
     * 是否为出货结果
     */
    function isOpenResult();

    /**
     * 出货是否成功
     */
    function isOpenResultOk();

    /**
     * 出货是否失败
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
     * 有没有包含电量信息
     */
    function hasBatteryValue();

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
     * @return mixed
     */
    function getRawData();

    /**
     * 获取数据
     * @return mixed
     */
    function getEncodeData();

    /**
     * 获取附加的命令，没有返回null
     */
    function getAttachedCMD(): ?ICmd;
}