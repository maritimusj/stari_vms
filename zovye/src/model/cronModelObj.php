<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\base\model;
use zovye\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;
use function zovye\tb;

/**
 * @method setJobUid(mixed $uid)
 * @method getSpec()
 * @method getJobUid()
 * @method getUid()
 * @method getTotal()
 */
class cronModelObj extends modelObj
{
    public static function getTableName($readOrWrite = modelObj::OP_WRITE): string
    {
        return tb('cron');
    }

	/** @var int */
	protected $id;

	/** @var int */
	protected $uniacid;

	/** @var varchar */
	protected $uid;

    /** @var varchar */
    protected $job_uid;

	/** @var varchar */
	protected $spec;

	/** @var int */
	protected $total;

	/** @var varchar */
	protected $url;

    /** @var varchar */
    protected $extra;

	/** @var int */
	protected $createtime;

    use ExtraDataGettersAndSetters;
}