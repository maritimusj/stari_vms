<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\base\modelObjFinder;
use zovye\model\login_dataModelObj;

class LoginData
{
    const AGENT = 0;
    const KEEPER = 1;
    const User = 2;

    const AGENT_WEB = 3;

    /**
     * @param $token
     * @param mixed $src
     * @return login_dataModelObj|null
     */
    public static function get($token, $src = null): ?login_dataModelObj
    {
        $condition = ['token' => $token];
        if (!empty($src)) {
            $condition['src'] = $src;
        }

        return m('login_data')->findOne($condition);
    }

    /**
     * @param array $cond
     * @return login_dataModelObj|null
     */
    public static function findOne(array $cond = []): ?login_dataModelObj
    {
        return self::query($cond)->findOne();
    }

    /**
     * @param array $cond
     * @return modelObjFinder
     */
    public static function query(array $cond = []): modelObjFinder
    {
        return m('login_data')->query($cond);
    }

    public static function agent(array $cond = []): modelObjFinder
    {
        return self::query(['src' => LoginData::AGENT])->where($cond);
    }

    public static function agentWeb(array $cond = []): modelObjFinder
    {
        return self::query(['src' => LoginData::AGENT_WEB])->where($cond);
    }

    public static function keeper(array $cond = []): modelObjFinder
    {
        return self::query(['src' => LoginData::KEEPER])->where($cond);
    }  

    public static function user(array $cond = []): modelObjFinder
    {
        return self::query(['src' => LoginData::User])->where($cond);
    }

    /**
     * @param array $data
     * @return login_dataModelObj|null
     */
    public static function create(array $data): ?login_dataModelObj
    {
        return m('login_data')->create($data);
    }
}
