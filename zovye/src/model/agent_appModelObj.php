<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\modelObj;

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
class agent_appModelObj extends modelObj
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

    public static function getTableName($readOrWrite): string
    {
        return tb('agent_app');
    }


    public function getTplMsgData(): array
    {
        $datetime = date('Y-m-d H:i:s', $this->createtime);

        return [
            'first' => ['value' => '代理商申请通知'],
            'keyword1' => ['value' => $this->name],
            'keyword2' => ['value' => $this->mobile],
            'remark' => ['value' => "区域：{$this->address}，推荐人：{$this->referee}，日期：{$datetime}"],
        ];
    }

}
