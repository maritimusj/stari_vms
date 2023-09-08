<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\modelObj;

use function zovye\tb;

/**
 * @method getRemark()
 * @method getPics()
 * @method getText()
 * @method setRemark($remark)
 * @method getDeviceId()
 * @method getUserId()
 */
class device_feedbackModelObj extends modelObj
{
    public static function getTableName($read_or_write): string
    {
        return tb('device_feedback');
    }

    /** @var int */
    protected $id;

    protected $uniacid;

    /** @var int */
    protected $user_id;

    protected $text;

    protected $pics;

    /** @var int */
    protected $device_id;

    protected $remark;

    /** @var int */
    protected $createtime;

}