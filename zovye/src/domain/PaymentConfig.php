<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\domain;

use zovye\base\ModelFactory;
use zovye\model\agentModelObj;
use zovye\model\payment_configModelObj;
use zovye\util\Helper;
use function zovye\err;
use function zovye\m;

class PaymentConfig extends AbstractBase
{
    public static function model(): ModelFactory
    {
        return m('payment_config');
    }

    public static function getByName($name)
    {
        return parent::findOne([
            'agent_id' => 0,
            'name' => $name,
        ]);
    }

    public static function removeByName($name): bool
    {
        return parent::remove([
            'agent_id' => 0,
            'name' => $name,
        ]);
    }

    public static function hasAny(agentModelObj $agent): bool
    {
        return parent::exists(['agent_id' => $agent->getId()]);
    }

    public static function getFor(agentModelObj $agent, $name)
    {
        return parent::findOne([
            'agent_id' => $agent->getId(),
            'name' => $name,
        ]);
    }

    public static function createOrUpdateByName($name, $extra = [])
    {
        return self::createOrUpdate(0, $name, $extra);
    }

    public static function createOrUpdate($agent_id, $name, $extra = [])
    {
        /** @var payment_configModelObj $config */
        $config = PaymentConfig::findOne([
            'agent_id' => $agent_id,
            'name' => $name,
        ]);

        if ($config) {
            $config->setExtraData($extra);
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
                'HTTP_USER_AGENT' => 'payment_notify',
            ],
            'op' => 'notify',
            'from' => $config->getName(),
            'config_id' => $config->getId(),
            'agent_id' => $config->getAgentId(),
        ]);

        if (empty($res)) {
            return err('无法创建支付回调文件！');
        }

        return $config;
    }
}