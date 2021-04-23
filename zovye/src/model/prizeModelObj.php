<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\model;

use zovye\base\modelObj;

use function zovye\tb;

/**
 * Class prizeModelObj
 * @package zovye
 * @method getOpenid()
 * @method getTitle()
 * @method getLink()
 * @method getImg()
 * @method getDesc()
 * @method getCreatetime()
 */
class prizeModelObj extends modelObj
{
    /** @var int */
    protected $id;
    protected $uniacid;
    protected $openid;
    protected $title;
    protected $link;
    protected $img;
    protected $desc;
    protected $createtime;

    public static function getTableName($readOrWrite): string
    {
        return tb('prize');
    }
}