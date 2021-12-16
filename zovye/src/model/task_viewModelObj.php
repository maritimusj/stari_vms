<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;
use function zovye\tb;

class task_viewModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('task_view');
    }
    
	protected $id;

	protected $user_id;

	protected $account_id;

	protected $s1;

	protected $extra;

	protected $createtime;

	use ExtraDataGettersAndSetters;
}