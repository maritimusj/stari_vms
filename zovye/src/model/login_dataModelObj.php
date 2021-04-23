<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\model;

use zovye\base\modelObj;

use function zovye\tb;

/**
 * @method int getSrc()
 * @method int getUserId()
 * @method string getToken()
 * @method string getSessionKey()
 * @method string getOpenidX()
 * @method int getCreatetime()
 * @method setOpenidX($getOpenid)
 */
class login_dataModelObj extends modelObj
{
    /** @var int */
    protected $id;
    protected $src;
    /** @var int */
    protected $user_id;
    protected $token;
    protected $session_key;
    protected $openid_x;
    /** @var int */
    protected $createtime;

    public static function getTableName($readOrWrite): string
    {
        return tb('login_data');
    }
}
