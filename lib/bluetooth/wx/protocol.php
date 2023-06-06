<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace bluetooth\wx;

use zovye\BlueToothProtocol;
use zovye\Contract\bluetooth\IBlueToothProtocol;
use zovye\Contract\bluetooth\ICmd;
use zovye\Contract\bluetooth\IResponse;
use function zovye\err;

class protocol implements IBlueToothProtocol
{
    public function getTitle(): string
    {
        return '第三方厂商蓝牙协议(wx v1.0)';
    }

    function support($fn): bool
    {
        if ($fn == BlueToothProtocol::QOE) {
            return true;
        }

        return false;
    }

    public function transUID($uid): string
    {
        return str_pad($uid, 12, '0', STR_PAD_LEFT);
    }

    /**
     * @param $device_id
     * @param $data
     * @return IResponse
     */
    public function parseResponse($device_id, $data): ?IResponse
    {
        return new response($data, true);
    }

    /**
     * 设备已连接
     * @param $device_id
     * @param string $data
     * @return ICmd
     */
    public function onConnected($device_id, $data = ''): ?ICmd
    {
        return Helper::newShakeHandMsg($device_id);
    }

    function initialize($device_id)
    {
        return err('无法连接设备，请联系管理员！');
    }

    /**
     * 出货
     * @param $device_id
     * @param $data
     * @return ICmd
     */
    public function open($device_id, $data): ?ICmd
    {
        if ($data['motor']) {
            return Helper::newOpenMsg($device_id, $data['motor'], 0);
        }

        return Helper::newOpenMsg($device_id, 0, $data['locker']);
    }

}