<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;
use function zovye\tb;

class principalModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('principal');
    }

    /** @var int */
	protected $id;

	/** @var int */
	protected $user_id;

	/** @var int */
	protected $principal_id;

	/** @var bool */
	protected $enable;

	/** @var string */
	protected $name;

	protected $extra;

	/** @var int */
	protected $createtime;

	use ExtraDataGettersAndSetters;
}