<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

class Topic
{
    /**
     * 返回指定频道的平台相关值
     * @param string $name
     * @return string
     */
    public static function encrypt(string $name = 'all'): string
    {
        static $app_key = null;
        if (is_null($app_key)) {
            $app_key = settings('ctrl.appKey');
        }

        return md5("{$app_key}{$name}");
    }
}
