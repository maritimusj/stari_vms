<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\domain;

use zovye\base\ModelFactory;
use zovye\model\agentModelObj;
use zovye\model\deviceModelObj;
use zovye\util\Helper;
use function zovye\err;
use function zovye\m;

class PaymentConfig extends AbstractBase
{
    public static function model(): ModelFactory
    {
        return m('payment_config');
    }

    public static function getFor(agentModelObj $agent = null, $name = '')
    {
        return parent::findOne([
            'agent_id' => $agent ? $agent->getId() : 0,
            'name' => $name,
        ]);
    }

    public static function getForDevice(deviceModelObj $device, $name)
    {
        return self::getFor($device->getAgent(), $name);
    }

    public static function createOrUpdate($agent_id, $name, $extra = [])
    {
        $config = PaymentConfig::findOne([
            'agent_id' => $agent_id,
            'name' => $name,
        ]);

        if ($config) {
            $config->setExtra($extra);
            if (!$config->save()) {
                return err('保存失败！');
            }
        } else {
            $config = PaymentConfig::create([
                'agent_id' => $agent_id,
                'name' => $name,
                'extra' => $extra,
            ]);
            if (empty($config)) {
                return err('创建失败！');
            }
        }
        //创建接口文件
        $res = Helper::createApiRedirectFile("payment/{$config->getId()}.php", 'payresult', [
            'headers' => [
                'HTTP_USER_AGENT' => 'wx_notify',
            ],
            'op' => 'notify',
            'from' => $config->getName(),
            'id' => $config->getId(),
        ]);

        if (empty($res)) {
            return err('无法创建支付回调文件！');
        }

        return true;
    }
}