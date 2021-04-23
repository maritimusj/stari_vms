<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\model;

use zovye\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;

use function zovye\tb;

/**
 * Class prizelistModelObj
 * @package zovye
 * @method getEnabled()
 * @method setEnabled($enabled)
 * @method getTitle()
 * @method setTitle($title)
 * @method getName()
 * @method setName($name)
 * @method getPercent()
 * @method setPercent($p)
 * @method getTotal()
 * @method setTotal($total)
 * @method getMaxCount()
 * @method setMaxCount($max)
 * @method getBeginTime()
 * @method setBeginTime($t)
 * @method getEndTime()
 * @method setEndTime($t)
 * @method getExtra()
 * @method getCreatetime()
 */
class prizelistModelObj extends modelObj
{
    /** @var int */
    protected $id;
    protected $uniacid;
    protected $enabled;
    protected $title;
    protected $name;
    protected $percent;
    protected $total;
    protected $max_count;
    protected $begin_time;
    protected $end_time;
    protected $extra;
    protected $createtime;

    use ExtraDataGettersAndSetters;

    public static function getTableName($readOrWrite): string
    {
        return tb('prizelist');
    }
}