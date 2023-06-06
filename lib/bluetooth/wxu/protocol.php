<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\wxu;

use zovye\Contract\bluetooth\IBlueToothProtocol;
use zovye\Contract\bluetooth\ICmd;
use zovye\Contract\bluetooth\IResponse;
use function zovye\err;

class protocol implements IBlueToothProtocol
{
    public function getTitle(): string
    {
        return '蓝牙售货机协议(wxu v1.1)';
    }

    public function transUID($uid)
    {
        return $uid;
    }

    public function onConnected($device_id, $data = ''): ?ICmd
    {
        return new ShakeHandCmd($device_id);
    }

    public function initialize($device_id)
    {
        return err('无法连接设备，请联系管理员！');
    }

    public function open($device_id, $data): ?ICmd
    {
        $locker = $data['locker'] ?? null;
        
        if (is_int($locker)) {
            return new OpenDeviceCmd($device_id, $locker);
        }

        return null;
    }

    public function parseResponse($device_id, $data): ?IResponse
    {
        return new response($device_id, $data);
    }
}