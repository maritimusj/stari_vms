<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\ModelObj;
use zovye\Pay;
use zovye\traits\ExtraDataGettersAndSetters;
use function zovye\is_error;
use function zovye\tb;

/**
 * @method getName()
 * @method getAgentId()
 */
class payment_configModelObj extends ModelObj
{
    public static function getTableName($read_or_write): string
    {
        return tb('payment_config');
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
    protected $extra;

    /** @var int */
    protected $createtime;

    use ExtraDataGettersAndSetters;

    public function isEnabled($app)
    {
        return $this->getExtraData("app.$app", false);
    }

    public function toArray(): array
    {
        $data = (array)$this->getExtraData();
        $data['config_id'] = $this->getId();

        if ($this->getName() == Pay::WX) {
            // v2版本使用curl请求api接口，php7版本只支持文件名指定证书
            $res = Pay::getPEMFile($data['pem']);
            if (!is_error($res)) {
                $data['pem']['cert'] = $res[0];
                $data['pem']['key'] = $res[1];
            }
        }

        return $data;
    }
}