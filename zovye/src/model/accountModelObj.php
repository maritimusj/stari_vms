<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\model;

use zovye\Account;
use zovye\base\modelObj;

use zovye\traits\ExtraDataGettersAndSetters;
use zovye\Util;
use zovye\WxPlatform;
use function zovye\tb;

/**
 * Class accountModelObj
 * @method int getAgentId()
 * @method setAgentId($agent_id)
 * @method string getUid()
 * @method setUid($uid)
 * @method string getName()
 * @method setName($name)
 * @method setTitle($title)
 * @method string getImg()
 * @method setImg($img)
 * @method string getQrcode()
 * @method setQrcode($qrcode)
 * @method string getClr()
 * @method setClr($clr)
 * @method int getCount()
 * @method setCount($count)
 * @method int getSccount()
 * @method setSccount($count)
 * @method string getScname()
 * @method setScname($name)
 * @method int getBalanceDeductNum()
 * @method setBalanceDeductNum($num)
 * @method int getTotal()
 * @method setTotal($total)
 * @method int getOrderLimits()
 * @method setOrderLimits($limits)
 * @method int getOrderNo()
 * @method setOrderNo($no)
 * @method string getGroupName()
 * @method setGroupName($group)
 * @method int getState()
 * @method void setState(int $state)
 * @method string getUrl()
 * @method setUrl($url)
 * @method int getShared()
 * @method setShared($shared)
 * @method getExtraData(string $string, int $int)
 */
class accountModelObj extends modelObj
{
    /** @var int */
    protected $id;

    /** @var int */
    protected $uniacid;

    /** @var int */
    protected $agent_id;

    /** @var string */
    protected $uid;

    /** @var string */
    protected $name;

    /** @var string */
    protected $title;

    /** @var string */
    protected $descr;

    /** @var string */
    protected $img;

    /** @var string */
    protected $qrcode;

    /** @var string */
    protected $clr;

    /** @var int */
    protected $count;

    /** @var int */
    protected $sccount;

    /** @var string */
    protected $scname;

    /** @var int */
    protected $balance_deduct_num;

    /** @var int */
    protected $total;

    /** @var int */
    protected $order_limits;

    /** @var int */
    protected $order_no;

    /** @var string */
    protected $group_name;

    /** @var int */
    protected $state;

    /** @var string */
    protected $url;

    /** @var bool */
    protected $shared;

    protected $extra;
    /** @var int */
    protected $createtime;

    use ExtraDataGettersAndSetters;

    public static function getTableName($readOrWrite): string
    {
        return tb('account');
    }

    public function isBanned(): bool
    {
        return $this->state == Account::BANNED;
    }

    public function getDescription(): string
    {
        return $this->descr ?: DEFAULT_ACCOUNT_DESC;
    }

    public function getMedia(): string
    {
        return $this->isVideo() ? $this->qrcode : '';
    }

    public function setMedia($url)
    {
        if ($this->isVideo()) {
            $this->setQrcode($url);
        }
    }

    public function getDuration(): int
    {
        return intval($this->settings('config.video.duration', 1));
    }

    public function setDuration($duration)
    {
        return $this->settings('config.video.duration', intval($duration));
    }

    public function balance(): int
    {
        return intval($this->getBalanceDeductNum());
    }

    public function getTitle(): string
    {
        return empty($this->title) ? $this->name : $this->title;
    }

    public function title(): string
    {
        return strval($this->getTitle());
    }

    public function name(): string
    {
        return strval($this->getName());
    }

    public function commission_price(): int
    {
        $commission = $this->get('commission', []);
        if ($commission) {
            return intval($commission['money']);
        }

        return 0;
    }

    public function format(): array
    {
        return Account::format($this);
    }

    public function isSpecial(): bool
    {
        return in_array($this->getType(), [
            Account::JFB,
            Account::MOSCALE,
            Account::YUNFENBA,
            Account::AQIINFO,
        ]);
    }

    public function getType(): int
    {
        if ($this->state != Account::BANNED) {
            return $this->state;
        }
        return intval($this->settings('config.type'));
    }

    public function isVideo(): bool
    {
        return $this->getType() == Account::VIDEO;
    }

    public function isJFB(): bool
    {
        return $this->getType() == Account::JFB;
    }

    public function isMoscale(): bool
    {
        return $this->getType() == Account::MOSCALE;
    }

    public function isYunfenba(): bool
    {
        return $this->getType() == Account::YUNFENBA;
    }

    public function isAQiinfo(): bool
    {
        return $this->getType() == Account::AQIINFO;
    }

    public function isAuth(): bool
    {
        return $this->getType() == Account::AUTH;
    }

    public function getAssignData(): array
    {
        return (array)$this->get('assigned', []);
    }

    public function destroy(): bool
    {
        $this->remove('qrcodesData');
        $this->remove('limits');
        $this->remove('commission');
        $this->remove('config');
        $this->remove('assigned');
        $this->remove('authdata');
        $this->remove('profile');

        return parent::destroy();
    }

    /**
     * 如果授权公众号，返回授权公众号类型
     * 0 订阅号
     * 1 其它订阅号
     * 2 服务号
     */
    public function getServiceType()
    {
        return $this->settings('profile.authorizer_info.service_type_info.id', 0);
    }

    /**
     * 使用这个授权服务号的二维码做为设备二维码，推送到APP上显示
     * @param null $enable
     * @return bool
     */
    public function useAccountQRCode($enable = null): bool
    {
        if (isset($enable)) {
            return $this->updateSettings('misc.useAccountQRCode', $enable ? 1 : 0);
        }
        return \zovye\App::useAccountQRCode() && boolval($this->settings('misc.useAccountQRCode', 0));
    }

    public function getOpenMsg($from, $to, $redirect_url = ''): string
    {
        $config = $this->settings('config.open', []);
        if ($config['msg']) {
            $str = strval($config['msg']);
            if ($redirect_url) {
                if (stripos($str, '{url}') !== false && stripos($str, '{/url}') !== false) {
                    $arr = explode('{url}', $str, 2);
                    $text = $arr[0];
                    $arr = explode('{/url}', $arr[1], 2);
                    $text .= '<a href="' . $redirect_url . '">' . $arr[0] . '</a>' . $arr[1];
                } else {
                    $text = str_replace('{url}', "<a href=\"{$redirect_url}\">这里</a>", $str);
                }
            } else {
                $text = $str;
            }

            return WxPlatform::createToUserTextMsg($from, $to, $text);

        } elseif ($config['news']) {
            $params = [
                'title' => strval($config['news']['title']),
                'desc' => strval($config['news']['desc']),
                'image' => Util::toMedia($config['news']['image']),
            ];
            if ($redirect_url) {
                $params['url'] = $redirect_url;
            }
            return WxPlatform::createToUserNewsMsg($from, $to, $params);
        }

        return '';
    }
}
