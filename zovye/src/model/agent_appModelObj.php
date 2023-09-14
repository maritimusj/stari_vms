<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\ModelObj;
use zovye\Wx;
use function zovye\tb;

/**
 * Class agent_appModelObj
 * @package zovye
 * @method getName()
 * @method getMobile()
 * @method getAddress()
 * @method getReferee()
 * @method getState()
 * @method setState($state)
 * @method getCreatetime()
 */
class agent_appModelObj extends ModelObj
{
    /** @var int */
    protected $id;
    protected $uniacid;
    protected $name;
    protected $mobile;
    protected $address;
    protected $referee;
    protected $state;
    protected $createtime;

    public static function getTableName($read_or_write): string
    {
        return tb('agent_app');
    }

    public function getTplMsgData(): array
    {
        return [
            'thing9' => ['value' => '合作申请'],
            'phrase25' => ['value' => '待审核'],
            'thing7' => ['value' => Wx::trim_thing($this->name)],
            'phone_number28' => ['value' => $this->mobile],
            'time3' => ['value' => date('Y-m-d H:i:s', $this->createtime)],
        ];
    }

}
