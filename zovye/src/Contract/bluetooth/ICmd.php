<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\Contract\bluetooth;

interface ICmd
{
    /**
     * 获取设备编号
     */
    function getDeviceID();

    /**
     * 获取命令代码
     */
    function getID();

    /**
     * 命令附加数据
     */
    function getData();

    /**
     * 返回原始格式的数据
     */
    function getRaw();

    /**
     * 命令的描述文本
     */
    function getMessage();

    /**
     * 返回指定格式编码后的数据
     */
    function getEncoded($fn = null);
}