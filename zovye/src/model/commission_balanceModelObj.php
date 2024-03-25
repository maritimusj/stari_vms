<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\ModelObj;
use zovye\We7;
use function zovye\getArray;
use function zovye\tb;

/**
 * Class commission_balanceModelObj
 * @method setExtra(string $data)
 * @method setUpdatetime(int $time)
 * @method getXVal()
 * @method getOpenid()
 * @method getSrc()
 * @method getUpdatetime()
 * @method getCreatetime()
 */
class commission_balanceModelObj extends ModelObj
{
    /** @var int */
    protected $id;

    protected $uniacid;

    /** @var string */
    protected $openid;

    /** @var int */
    protected $src;

    /** @var int */
    protected $x_val;

    protected $extra;

    /** @var int */
    protected $createtime;

    /** @var int */
    protected $updatetime;

    public static function getTableName($read_or_write): string
    {
        return tb('commission_balance');
    }

    public function update(array $data = [], $update = false): bool
    {
        if ($data) {
            if (We7::is_serialized($this->extra)) {
                $this->extra = We7::deserialize($this->extra);
                if (!is_array($this->extra)) {
                    $this->extra = [];
                }
            }
            $data = array_merge($this->extra, $data);
            $this->setExtra(serialize($data));
        }

        if ($update) {
            $this->setUpdatetime(time());
        }

        return $this->save();
    }

    public function getExtraData($name = null, $default = null)
    {
        if (We7::is_serialized($this->extra)) {
            $this->extra = We7::deserialize($this->extra);
        }

        if (empty($name)) {
            return $this->extra;
        }

        return getArray($this->extra, $name, $default);
    }

    public function getState(): string
    {
        $state = $this->getExtraData('state');

        if (empty($state)) {
            $status = '（审核中）';
        } elseif ($state == 'mchpay') {
            $status = '（成功）';
        } elseif ($state == 'confirmed') {
            $status = '（完成）';
        } elseif ($state == 'cancelled') {
            $status = '（已退款）';
        } else {
            $status = '（未知）';
        }

        return $status;
    }
}
