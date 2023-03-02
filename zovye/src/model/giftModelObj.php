<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use function zovye\tb;
use zovye\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;

/**
 * @method setAgentId(int $param)
 * @method setName(mixed $name)
 * @method setDescription(mixed $description)
 * @method setImage(mixed $image)
 */
class giftModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('gift');
    }
    
	public static function debugMode(): bool
	{
	    return true;
	}

	/** @var int */
	protected $id;

	/** @var int */
	protected $uniacid;

	/** @var int */
	protected $agent_id;

	/** @var string */
	protected $name;

	/** @var string */
	protected $description;

	protected $extra;

	/** @var int */
	protected $createtime;

	use ExtraDataGettersAndSetters;

}