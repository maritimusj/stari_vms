<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use function zovye\tb;

class withdraw_vwModelObj extends commission_balanceModelObj
{
    public static function getTableName($read_or_write): string
    {
        return tb('withdraw_vw');
    }
    
	/** @var varchar */
	protected $name;

	/** @var varchar */
	protected $nickname;

	/** @var varchar */
	protected $avatar;

	/** @var varchar */
	protected $mobile;
}