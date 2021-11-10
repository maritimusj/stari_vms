<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\base\modelObj;

use function zovye\tb;

class settings_userModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('settings_user');
    }

    /** @var int */
	protected $id;
	protected $uniacid;
	protected $name;
	protected $data;
	/** @var int */
	protected $createtime;
	protected $lock_uid;

}