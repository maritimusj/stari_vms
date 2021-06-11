<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */
namespace zovye\model;

use zovye\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;
use function zovye\tb;

class component_userModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('component_user');
    }

    /** @var int */
	protected $id;

    /** @var int */
	protected $uniacid;

        /** @var int */
	protected $user_id;

    /** @var string */
	protected $appid;

    /** @var string */
	protected $openid;

	protected $extra;

    /** @var int */
	protected $createtime;

	use ExtraDataGettersAndSetters;
}