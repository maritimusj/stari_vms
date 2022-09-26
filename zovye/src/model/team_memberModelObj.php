<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\base\modelObj;
use function zovye\tb;

class team_memberModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('team_member');
    }
    /** @var int */
	protected $team_id;

    /** @var int */
	protected $user_id;

    /** @var string */
	protected $mobile;

    /** @var string */
	protected $name;

    /** @var int */
	protected $createtime;

}