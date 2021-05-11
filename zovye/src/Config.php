<?php

namespace zovye;

class Config
{
    /**
     * 天猫拉新相关配置
     */
    public static function aliTicket($key, $v = null, $update = false)
    {
        if ($update) {
            return updateGlobalConfig('ali_ticket', $key, $v);
        }

        return globalConfig('ali_ticket', $key, $v);
    }

}