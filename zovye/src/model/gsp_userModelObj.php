<?php


namespace zovye\model;


use zovye\base\modelObj;
use zovye\GSP;

use function zovye\tb;

/**
 * @method getUid()
 * @method getVal()
 * @method getValType()
 */
class gsp_userModelObj extends modelObj
{
    /** @var int */
    protected $id;
    /** @var int */
    protected $agent_id;
    /** @var string */
    protected $uid;
    /** @var string */
    protected $val_type;
    /** @var int */
    protected $val;
    protected $order_types;
    /** @var int */
    protected $createtime;

    public static function getTableName($readOrWrite): string
    {
        return tb('gsp_user');
    }

    public function isRole(): bool
    {
        return in_array($this->uid, [GSP::LEVEL1, GSP::LEVEL2, GSP::LEVEL3]);
    }

    public function isFreeOrderIncluded(): bool
    {
        return stristr($this->order_types, 'f') !== false;
    }

    public function isPayOrderIncluded(): bool
    {
        return stristr($this->order_types, 'p') !== false;
    }
}