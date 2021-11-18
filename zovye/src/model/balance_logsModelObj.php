<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;
use function zovye\tb;

class balance_logsModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('balance_logs');
    }

    /** @var int */
    protected $id;

    /** @var int */
	protected $user_id;

    /** @var int */
	protected $account_id;

	protected $extra;

    /** @var int */
	protected $createtime;

	use ExtraDataGettersAndSetters;
}