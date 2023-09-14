<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\base\ModelObj;
use zovye\traits\ExtraDataGettersAndSetters;
use function zovye\tb;

/**
 * @method setJobUid(mixed $uid)
 * @method getSpec()
 * @method getJobUid()
 * @method getUid()
 * @method getTotal()
 */
class cronModelObj extends ModelObj
{
    public static function getTableName($read_or_write = ModelObj::OP_WRITE): string
    {
        return tb('cron');
    }

	/** @var int */
	protected $id;

	/** @var int */
	protected $uniacid;

	/** @var string */
	protected $uid;

    /** @var string */
    protected $job_uid;

	/** @var string */
	protected $spec;

	/** @var int */
	protected $total;

	/** @var string */
	protected $url;

    /** @var string */
    protected $extra;

	/** @var int */
	protected $createtime;

    use ExtraDataGettersAndSetters;
}