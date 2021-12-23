<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use DateTime;
use zovye\Balance;
use zovye\Locker;
use zovye\Pay;
use zovye\We7;
use zovye\User;
use zovye\Util;
use zovye\Agent;
use zovye\App;
use zovye\Order;
use zovye\State;
use zovye\WxMCHPay;
use zovye\LoginData;
use zovye\RowLocker;
use zovye\We7credit;
use zovye\base\modelObj;
use zovye\CommissionBalance;
use zovye\Principal;

use function zovye\m;
use function zovye\tb;
use function zovye\settings;
use function zovye\error;
use function zovye\is_error;

/**
 * Class userModelObj.
 *
 * @method getState()
 * @method getOpenid()
 * @method getNickname()
 * @method setNickname($name)
 * @method getAvatar()
 * @method setAvatar($url)
 * @method getMobile()
 * @method setMobile($mobile)
 * @method getSuperiorId()
 * @method setPassport(string $passport)
 * @method setState(int $param)
 * @method setSuperiorId(int $getId)
 * @method getAgentId()
 * @method getCreatetime()
 * @method setLockedUid(string $UNLOCKED)
 *
 * @property login_dataModelObj $loginData
 */
class userModelObj extends modelObj
{
    const UNLOCKED = 'n/a';

    /** @var int */
    protected $id;

    /** @var int */
    protected $uniacid;

    /** @var int */
    protected $state;

    /** @var int */
    protected $app;

    /** @var string */
    protected $openid;

    /** @var string */
    protected $nickname;

    /** @var string */
    protected $avatar;

    /** @var string */
    protected $mobile;

    /** @var string */
    protected $passport;

    /** @var int */
    protected $superior_id;

    /** @var int */
    protected $createtime;

    /** @var string */
    protected $locked_uid;

    private $loginData = null;

    public static function getTableName($readOrWrite): string
    {
        return tb('user');
    }

    //overwrite
    //因为users_vw继续user，所以在这里要绑定settings一些参数
    protected function getSettingsKey($key, string $classname = ''): string
    {
        return parent::getSettingsKey($key, userModelObj::class);
    }

    protected function getSettingsBindClassName(): string
    {
        return 'user';
    }

    public function isAliUser(): bool
    {
        return $this->app == User::ALI;
    }

    public function isWxUser(): bool
    {
        return $this->app == User::WX;
    }

    public function isWXAppUser(): bool
    {
        return $this->app == User::WxAPP;
    }

    public function isApiUser(): bool
    {
        return $this->app == User::API;
    }

    public function isThirdAccountUser(): bool
    {
        return $this->app == User::THIRD_ACCOUNT;
    }

    public function isDouYinUser(): bool
    {
        return $this->app == User::DouYin;
    }    

    public function profile($detail = true): array
    {
        $data = [
            'id' => $this->getId(),
            'openid' => $this->getOpenid(),
            'name' => $this->getName(),
            'nickname' => $this->getNickname(),
            'headimgurl' => $this->getAvatar(),
        ];

        if ($detail) {
            $fans_data = $this->get('fansData', []);
            if (is_array($fans_data)) {
                $data['sex'] = $fans_data['sex'];
                $data['province'] = $fans_data['province'];
                $data['city'] = $fans_data['city'];
                $data['country'] = $fans_data['country'];

                if (is_array($fans_data['tag'])) {
                    if (empty($data['province'])) {
                        $data['province'] = $fans_data['tag']['province'];
                    }
                    if (empty($data['city'])) {
                        $data['city'] = $fans_data['tag']['city'];
                    }
                    if (empty($data['province'])) {
                        $data['country'] = $fans_data['tag']['country'];
                    }
                }
            }
            $data['mobile'] = $this->getMobile();
        }

        return $data;
    }

    /**
     * 如果是代理商或者合伙人，则返回姓名，否则返回微信昵称.
     *
     * @return string
     */
    public function getName(): string
    {
        if ($this->isAgent()) {
            $name = $this->settings('agentData.name', '');
        } elseif ($this->isPartner()) {
            $name = $this->settings('partnerData.name', '');
        }

        return empty($name) ? $this->nickname : strval($name);
    }

    /**
     * 用户是否是代理商.
     *
     * @return bool
     */
    public function isAgent(): bool
    {
        return $this->is(User::AGENT);
    }

    /**
     * 返回用户代理商身份.
     *
     * @return agentModelObj
     */
    public function getAgent(): ?agentModelObj
    {
        if ($this->isAgent()) {
            return $this->agent();
        }

        return null;
    }

    /**
     * 是否是指定身份.
     *
     * @param $principal
     *
     * @return bool
     */
    public function is($principal): bool
    {
        $names = is_array($principal) ? $principal : explode(',', $principal);
        return array_intersect($names, $this->getPrincipals()) === $names;
    }

    /**
     * @return array
     */
    public function getPrincipals(): array
    {
        return $this->passport ? explode(',', $this->passport) : [];
    }

    /**
     * 用户是否是合伙人.
     *
     * @return bool
     */
    public function isPartner(): bool
    {
        return $this->is(User::PARTNER);
    }

    public function isIDCardVerified(): bool
    {
        return !empty($this->settings('idcard.verified'));
    }

    public function setIDCardVerified($hash): bool
    {
        if (empty($hash)) {
            return $this->updateSettings('idcard.verified', []);
        }

        return $this->updateSettings(
            'idcard.verified',
            [
                'matched' => true,
                'hash' => $hash,
                'createdAt' => time(),
            ]
        );
    }

    public function payLog($order_id, $data): bool
    {
        return $this->log(LOG_PAY, $order_id, $data);
    }

    /**
     * @param $order_id
     *
     * @return pay_logsModelObj|modelObj
     */
    public function getPayLog($order_id)
    {
        if ($order_id) {
            return m('pay_logs')->findOne(We7::uniacid(['title' => $order_id]));
        }

        return null;
    }

    /**
     * @return agentModelObj|null
     */
    public function getSuperior(): ?agentModelObj
    {
        if ($this->superior_id) {
            return Agent::get($this->superior_id);
        }

        return null;
    }

    /**
     * @return agentModelObj
     */
    public function agent(): agentModelObj
    {
        $classname = m('agent')->objClassname();
        /** @var agentModelObj $agent */
        $agent = new $classname($this->id, $this->factory());

        return $agent->__setData(
            $this->__getData(
                function () {
                    return true;
                }
            )
        );
    }

    /**
     * 获取小程序登录数据.
     *
     * @return login_dataModelObj|null
     */
    public function getLoginData(): ?login_dataModelObj
    {
        if (!isset($this->loginData)) {
            $this->loginData = LoginData::findOne(['user_id' => $this->id]) ?: null;
        }

        return $this->loginData;
    }

    /**
     * 是否已被禁用.
     *
     * @return bool
     */
    public function isBanned(): bool
    {
        return $this->state == 1;
    }

    public function isKeeper(): bool
    {
        return $this->is(User::KEEPER);
    }

    public function setKeeper($beKeeper = true): bool
    {
        return $beKeeper ? $this->setPrincipal(User::KEEPER) : $this->removePrincipal(User::KEEPER);
    }

    public function isTester(): bool
    {
        return $this->is(User::TESTER);
    }

    public function setTester($beTester = true): bool
    {
        return $beTester ? $this->setPrincipal(User::TESTER) : $this->removePrincipal(User::TESTER);
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function setPrincipal($name): bool
    {
        if ($name) {
            $names = is_array($name) ? $name : explode(',', $name);
            $arr = array_merge($this->getPrincipals(), $names);

            $this->setPassport(implode(',', array_unique($arr)));

            return $this->save() && Principal::update($this);
        }

        return false;
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function removePrincipal($name): bool
    {
        if ($name) {
            $names = is_array($name) ? $name : explode(',', $name);
            $arr = array_diff($this->getPrincipals(), $names);

            $this->setPassport(implode(',', $arr));

            return $this->save() && Principal::update($this);
        }

        return false;
    }

    /**
     * @return modelObj|keeperModelObj
     */
    public function getKeeper()
    {
        return m('keeper')->findOne(['mobile' => $this->getMobile()]);
    }

    /**
     * 是否是佣金用户.
     *
     * @return bool
     */
    public function isGSPor(): bool
    {
        return $this->is(User::GSPOR);
    }

    /**
     * 设置用户为代理商.
     *
     * @param $level
     *
     * @return bool
     */
    public function setAgent($level): bool
    {
        $levels = settings('agent.levels');
        if ($level) {
            if (array_key_exists($level, $levels)) {
                if ($this->isAgent()) {
                    $this->removePrincipal(User::AGENT);
                    $this->removePrincipal(array_keys($levels));
                }

                if ($this->setPrincipal([User::AGENT, $level])) {
                    return true;
                }
            }
        } else {
            $res = We7::pdo_update(User::getTableName(), ['superior_id' => 0], ['superior_id' => $this->getId()]);
            if ($res !== false) {
                return $this->removePrincipal(User::AGENT) && $this->removePrincipal(array_keys($levels));
            }
        }

        return false;
    }

    /**
     * 获取合伙人的代理商.
     *
     * @return agentModelObj
     */
    public function getPartnerAgent(): ?agentModelObj
    {
        if ($this->isPartner()) {
            $partner_data = $this->get('partnerData', []);
            if ($partner_data['agent']) {
                $agent = Agent::get(intval($partner_data['agent']));
                if ($agent) {
                    return $agent;
                }
            }
        }

        return null;
    }

    /**
     * 获取用户的微擎积分.
     *
     * @return We7credit
     */
    public function getWe7credit(): We7credit
    {
        return new We7credit($this);
    }

    /**
     * 增加或者减少用户佣金.
     *
     * @param $price
     * @param $src
     * @param array $extra
     *
     * @return commission_balanceModelObj
     */
    public function commission_change($price, $src, array $extra = []): ?commission_balanceModelObj
    {
        $balance = $this->getCommissionBalance();

        $r = $balance->change($price, $src, $extra);
        if ($r) {
            $this->setPrincipal(User::GSPOR);
            return $r;
        }

        return null;
    }

    /**
     * 获取用户名佣金帐户.
     *
     * @return CommissionBalance
     */
    public function getCommissionBalance(): CommissionBalance
    {
        return new CommissionBalance($this);
    }

    /**
     * 获取用户名积分帐户.
     *
     * @return Balance
     */
    public function getBalance(): Balance
    {
        return new Balance($this);
    }

    public function isSigned(): bool
    {
        return Util::expiredCallUtil("daily:sign_in:{$this->getId()}", new DateTime('next day 00:00'), function() {
            if ($this->getBalance()->log()->where([
                'src' => Balance::SIGN_IN_BONUS,
                'createtime >=' => strtotime('today 00:00'),
                'createtime <' => strtotime('next day 00:00'),
            ])->count() > 0) {
                return true;
            }
            return false;
        });
    }

    public function signIn($val): bool
    {
        $res = $this->getBalance()->change($val, Balance::SIGN_IN_BONUS, [
            'date' => date('Y-m-d'),
            'user-agent' => $_SERVER['HTTP_USER_AGENT'],
            'ip' => $this->getLastActiveData('ip') ?: Util::getClientIp(),
        ]);
        if (empty($res)) {
            return false;
        }
        Util::expire("daily:sign_in:{$this->getId()}");
        return true;
    }

    /**
     * 用户今日免费领取数.
     *
     * @return int
     */
    public function getTodayFreeTotal(): int
    {
        $condition = [
            'openid' => $this->openid,
            'createtime >=' => strtotime('today'),
        ];

        if (App::isBalanceEnabled() && Balance::isFreeOrder()) {
            $condition['src'] = [Order::ACCOUNT, Order::BALANCE];
        } else {
            $condition['src'] = Order::ACCOUNT;
        }

        $query = Order::query($condition);
        $res = $query->get('sum(num)');

        return intval($res);
    }

    /**
     * 统计用户免费领取的数量.
     *
     * @return int
     */
    public function getFreeTotal(): int
    {
        $condition = [
            'openid' => $this->openid,
        ];
        if (App::isBalanceEnabled() && Balance::isFreeOrder()) {
            $condition['src'] = [Order::ACCOUNT, Order::BALANCE];
        } else {
            $condition['src'] = Order::ACCOUNT;
        }
        
        $query = Order::query($condition);
        $res = $query->get('sum(num)');

        return intval($res);
    }

    /**
     * 统计用户支付领取的数量.
     *
     * @return int
     */
    public function getPayTotal(): int
    {
        $condition = ['openid' => $this->openid];
        
        if (App::isBalanceEnabled() && Balance::isPayOrder()) {
            $condition['src'] = [Order::PAY, Order::BALANCE];
        } else {
            $condition['src'] = Order::PAY;
        }

        $query = Order::query($condition);
        $res = $query->get('sum(num)');

        return intval($res);
    }

    /**
     * 新的锁定方法
     * @param string $name
     * @return lockerModelObj|null
     */
    public function acquireLocker(string $name = ''): ?lockerModelObj
    {
        return Locker::try("user:{$this->getId()}:$name", REQUEST_ID);
    }

    /**
     * 锁定用户.
     *
     * @return ?RowLocker
     */
    public function lock(): ?RowLocker
    {
        return Util::lockObject($this, [OBJ_LOCKED_UID => self::UNLOCKED], true);
    }

    public function isLocked(): bool
    {
        return $this->locked_uid != self::UNLOCKED;
    }

    public function unlock(): bool
    {
        $this->setLockedUid(self::UNLOCKED);
        return $this->save();
    }

    /**
     * 给用户打款.
     *
     * @param $n
     * @param $trade_no
     * @param string $desc
     *
     * @return array
     */
    public function MCHPay($n, $trade_no, string $desc = ''): array
    {
        if ($trade_no && $n > 0) {
            $params = Pay::getDefaultPayParams('wx');
            if (empty($params)) {
                return error(State::ERROR, '没有配置微信打款信息！');
            }

            $file = Pay::getPEMFile($params['pem']);
            if (is_error($file)) {
                return $file;
            }

            $params['pem']['cert'] = $file['cert_filename'];
            $params['pem']['key'] = $file['key_filename'];

            $res = (new WxMCHPay($params))->transferTo($this->openid, $trade_no, $n, $desc);
            if (is_error($res)) {
                return $res;
            }

            if ($res && $res['partner_trade_no'] == $trade_no && isset($res['payment_no'])) {
                return $res;
            } else {
                return error(State::ERROR, '打款失败！');
            }
        }

        return error(State::ERROR, '参数不正确！');
    }

    public function setLastActiveData($data = []): bool
    {
        return $this->updateSettings('last.active', $data);
    }

    public function getLastActiveData($name = '', $default = null)
    {
        if (empty($name)) {
            return $this->settings('last.active', $default);
        }
        return $this->settings("last.active.$name", $default);
    }

    public function isWxAppAllowed($appID): bool
    {
        $agent = null;
        if ($this->isAgent()) {
            $agent = $this->agent();
        } elseif ($this->isPartner()) {
            $agent = $this->getPartnerAgent();
        } elseif ($this->isKeeper()) {
            $keeper = $this->getKeeper();
            if ($keeper) {
                $agent = $keeper->getAgent();
            }
        }
        if ($agent) {
            $config = $agent->agentData('wx.app', []);
            return empty($config) || empty($config['key']) || $config['key'] === $appID;
        }
        return $appID === settings('agentWxapp.key', '');
    }
}
