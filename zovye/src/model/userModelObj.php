<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use DateTime;
use DateTimeImmutable;
use zovye\App;
use zovye\base\ModelObj;
use zovye\business\ChargingNowData;
use zovye\business\Fueling;
use zovye\business\UserCommissionBalanceCard;
use zovye\business\VIPCard;
use zovye\contract\ICard;
use zovye\domain\Account;
use zovye\domain\Agent;
use zovye\domain\Balance;
use zovye\domain\CommissionBalance;
use zovye\domain\Device;
use zovye\domain\Keeper;
use zovye\domain\Locker;
use zovye\domain\LoginData;
use zovye\domain\Order;
use zovye\domain\PayLogs;
use zovye\domain\Principal;
use zovye\domain\Referral;
use zovye\domain\User;
use zovye\util\CacheUtil;
use zovye\util\Util;
use zovye\We7;
use zovye\We7credit;
use function zovye\m;
use function zovye\settings;
use function zovye\tb;

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
 * @method setState(int $param)
 * @method setSuperiorId(int $getId)
 * @method getAgentId()
 * @method getCreatetime()
 * @method setLockedUid(string $UNLOCKED)
 * @method setOpenid($getOpenid)
 * @method getApp()
 * @method setS1(string $s1)
 *
 * @property login_dataModelObj $loginData
 */
class userModelObj extends ModelObj
{
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
    protected $s1;

    /** @var int */
    protected $superior_id;

    /** @var int */
    protected $createtime;

    private $loginData = null;

    public static function getTableName($read_or_write): string
    {
        return tb('user');
    }

    //overwrite
    //因为user有继承类，所以在这里要绑定settings一些参数
    protected function getSettingsKey($key, string $classname = ''): string
    {
        return parent::getSettingsKey($key, userModelObj::class);
    }

    protected function getSettingsBindClassName(): string
    {
        return 'user';
    }

    public function isAccessible(): bool
    {
        return $this->uniacid == We7::uniacid();
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

    public function isPromoUser(): bool
    {
        return $this->app == User::PROMO;
    }

    public function isDouYinUser(): bool
    {
        return $this->app == User::DouYin;
    }

    public function isPseudoUser(): bool
    {
        return $this->app == User::PSEUDO;
    }

    public function profile($detail = true): array
    {
        $data = [
            'id' => $this->getId(),
            'openid' => $this->getOpenid(),
            'name' => $this->getName(),
            'nickname' => $this->getNickname(),
            'headimgurl' => $this->getAvatar(),
            'mobile' => $this->getMobile(),
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

        return strval(empty($name) ? $this->nickname : $name);
    }

    /**
     * 用户是否是代理商.
     *
     * @return bool
     */
    public function isAgent(): bool
    {
        return Principal::is($this, Principal::Agent);
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
     * 用户是否是合伙人.
     *
     * @return bool
     */
    public function isPartner(): bool
    {
        return Principal::is($this, Principal::Partner);
    }

    public function isIDCardVerified(): bool
    {
        return !empty($this->settings('idcard.verified'));
    }

    public function getIDCardVerifiedData()
    {
        return $this->settings('idcard.verified.data', []);
    }

    public function setIDCardVerified($hash = '', $data = []): bool
    {
        if (empty($hash)) {
            return $this->updateSettings('idcard.verified', []);
        }

        return $this->updateSettings(
            'idcard.verified',
            [
                'matched' => true,
                'hash' => $hash,
                'data' => $data,
                'createdAt' => time(),
            ]
        );
    }

    public function payLog($order_id, $level, $data): bool
    {
        return $this->log($level, $order_id, $data);
    }

    /**
     * @param $order_id
     *
     * @return pay_logsModelObj|ModelObj
     */
    public function getPayLog($order_id)
    {
        if ($order_id) {
            return PayLogs::findOne(['title' => $order_id]);
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
        return Principal::is($this, Principal::Keeper);
    }

    public function setKeeper($beKeeper = true): bool
    {
        return $beKeeper ? $this->setPrincipal(Principal::Keeper)
            : $this->removePrincipal(Principal::Keeper);
    }

    public function isTester(): bool
    {
        return Principal::is($this, Principal::Tester);
    }

    public function setTester($beTester = true): bool
    {
        return $beTester ? $this->setPrincipal(Principal::Tester)
            : $this->removePrincipal(Principal::Tester);
    }

    /**
     * @param $principal_id
     * @param array $extra
     * @return bool
     */
    public function setPrincipal($principal_id, array $extra = []): bool
    {
        if ($principal_id) {
            $principal = Principal::findOne([
                'user_id' => $this->getId(),
                'principal_id' => $principal_id,
            ]);

            if ($principal) {
                $principal->setEnabled(true);
                $principal->setName($this->getName());
                $principal->setExtraData($extra);

                return $principal->save();
            }

            return (bool)Principal::create([
                'user_id' => $this->getId(),
                'principal_id' => $principal_id,
                'name' => $this->getName(),
                'enabled' => true,
                'extra' => $extra,
            ]);
        }

        return false;
    }

    /**
     * @param $principal_id
     * @return bool
     */
    public function removePrincipal($principal_id): bool
    {
        if ($principal_id) {
            return Principal::delete([
                'user_id' => $this->getId(),
                'principal_id' => $principal_id,
            ]);
        }

        return false;
    }

    /**
     * @return ModelObj|keeperModelObj
     */
    public function getKeeper()
    {
        return Keeper::findOne(['mobile' => $this->getMobile()]);
    }

    /**
     * 是否是佣金用户.
     *
     * @return bool
     */
    public function isGSPor(): bool
    {
        return Principal::is($this, Principal::Gspor);
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
        $levels = settings('agent.levels', []);
        if ($level) {
            if (array_key_exists($level, $levels)) {
                if ($this->isAgent()) {
                    $this->removePrincipal(Principal::Agent);
                }

                if ($this->setPrincipal(Principal::Agent, ['agent.level' => $level])) {
                    return true;
                }
            }
        } else {
            $res = We7::pdo_update(User::getTableName(), ['superior_id' => 0], ['superior_id' => $this->getId()]);
            if ($res !== false) {
                return $this->removePrincipal(Principal::Agent);
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
     * @param int $price
     * @param int $src
     * @param array $extra
     *
     * @return commission_balanceModelObj
     */
    public function commission_change(int $price, int $src, array $extra = []): ?commission_balanceModelObj
    {
        $balance = $this->getCommissionBalance();

        $r = $balance->change($price, $src, $extra);
        if ($r) {
            $this->setPrincipal(Principal::Gspor);

            return $r;
        }

        return null;
    }

    /**
     * 获取用户名佣金账户
     *
     * @return CommissionBalance
     */
    public function getCommissionBalance(): CommissionBalance
    {
        return new CommissionBalance($this);
    }

    /**
     * 获取用户名积分账户
     *
     * @return Balance
     */
    public function getBalance(): Balance
    {
        return new Balance($this);
    }

    public function isSigned(): bool
    {
        return CacheUtil::expiredCallUtil(
            "daily:sign_in:{$this->getId()}",
            new DateTime('next day 00:00'),
            function () {
                if ($this->getBalance()->log()->where([
                        'src' => Balance::SIGN_IN_BONUS,
                        'createtime >=' => (new DateTimeImmutable('00:00'))->getTimestamp(),
                        'createtime <' => (new DateTimeImmutable('next day 00:00'))->getTimestamp(),
                    ])->count() > 0) {
                    return true;
                }

                return false;
            }
        );
    }

    public function signIn($val): bool
    {
        $res = $this->getBalance()->change($val, Balance::SIGN_IN_BONUS, [
            'date' => date('Y-m-d'),
            'user-agent' => $_SERVER['HTTP_USER_AGENT'],
            'ip' => $this->getLastActiveIp(),
        ]);

        if (empty($res)) {
            return false;
        }

        CacheUtil::expire("daily:sign_in:{$this->getId()}");

        return true;
    }

    protected function getOrderGoodsTotal(int $start, int $end, string $way, int $goods_id = 0): int
    {
        $condition = [
            'openid' => $this->openid,
        ];

        if ($start > 0) {
            $condition['createtime >='] = $start;
        }

        if ($end > 0) {
            $condition['createtime <'] = $end;
        }

        if ($goods_id > 0) {
            $condition['goods_id'] = $goods_id;
        }

        if ($way == Order::FREE_STR) {
            if (App::isBalanceEnabled() && Balance::isFreeOrder()) {
                $condition['src'] = [Order::ACCOUNT, Order::FREE, Order::BALANCE];
            } else {
                $condition['src'] = [Order::ACCOUNT, Order::FREE];
            }
        } elseif ($way == Order::PAY_STR) {
            if (App::isBalanceEnabled() && Balance::isPayOrder()) {
                $condition['src'] = [Order::PAY, Order::BALANCE];
            } else {
                $condition['src'] = Order::PAY;
            }
        }

        $query = Order::query($condition);
        $res = $query->get('sum(num)');

        return intval($res);
    }

    /**
     * 用户今日免费领取数.
     *
     * @param int $goods_id 指定商品
     * @return int
     */
    public function getTodayFreeTotal(int $goods_id = 0): int
    {
        if (empty(Order::getFirstOrderOfUser($this))) {
            return 0;
        }

        return $this->getOrderGoodsTotal(
            (new DateTimeImmutable('00:00'))->getTimestamp(),
            0,
            Order::FREE_STR,
            $goods_id
        );
    }

    /**
     * 统计用户免费领取的数量.
     *
     * @param int $goods_id 指定商品
     * @return int
     */
    public function getFreeTotal(int $goods_id = 0): int
    {
        if (empty(Order::getFirstOrderOfUser($this))) {
            return 0;
        }

        return $this->getOrderGoodsTotal(0, 0, Order::FREE_STR, $goods_id);
    }

    /**
     * 统计用户今日支付领取的数量.
     *
     * @param int $goods_id 指定商品
     * @return int
     */
    public function getTodayPayTotal(int $goods_id = 0): int
    {
        if (empty(Order::getFirstOrderOfUser($this))) {
            return 0;
        }

        return $this->getOrderGoodsTotal(
            (new DateTimeImmutable('00:00'))->getTimestamp(),
            0,
            Order::PAY_STR,
            $goods_id
        );
    }

    public function getPayTotal(int $goods_id = 0): int
    {
        if (empty(Order::getFirstOrderOfUser($this))) {
            return 0;
        }

        return $this->getOrderGoodsTotal(0, 0, Order::PAY_STR, $goods_id);
    }

    /**
     * 新的锁定方法
     * @param string $name
     * @return lockerModelObj|null
     */
    public function acquireLocker(string $name = ''): ?lockerModelObj
    {
        return Locker::try("user:{$this->getId()}:$name");
    }

    public function recharge(pay_logsModelObj $pay_log)
    {
        return CommissionBalance::recharge($this, $pay_log);
    }

    public function cleanLastActiveData($key = ''): bool
    {
        if (empty($key)) {
            return $this->remove('last');
        }

        return $this->updateSettings("last.$key", null);
    }

    /**
     * 设置用户最后活动数据
     */
    public function setLastActiveData(string $key, $val): bool
    {
        if ($val instanceof ModelObj) {
            $val = [
                'id' => $val->getId(),
                'time' => TIMESTAMP,
            ];
        }

        return $this->updateSettings("last.$key", $val);
    }

    public function getLastActiveData($key = '', $default = null)
    {
        if (empty($key)) {
            return $this->get('last', $default);
        }

        return $this->settings("last.$key", $default);
    }

    public function getLastActiveIp(): string
    {
        return $this->getLastActiveData('ip', CLIENT_IP);
    }

    public function setLastActiveDevice(deviceModelObj $device = null): bool
    {
        if ($device) {
            return $this->setLastActiveData('ip', CLIENT_IP)
                && $this->setLastActiveData(deviceModelObj::class, $device);
        }

        return $this->cleanLastActiveData(deviceModelObj::class);
    }

    public function getLastActiveDevice($timeout = VISIT_DATA_TIMEOUT): ?deviceModelObj
    {
        $data = $this->getLastActiveData(deviceModelObj::class, []);
        if ($data && $data['id'] > 0 && TIMESTAMP - $data['time'] < $timeout) {
            return Device::get($data['id']);
        }

        return null;
    }

    public function setLastActiveAccount(accountModelObj $account = null): bool
    {
        if ($account) {
            return $this->setLastActiveData('ip', CLIENT_IP)
                && $this->setLastActiveData(accountModelObj::class, $account);
        }

        return $this->cleanLastActiveData(accountModelObj::class);
    }

    public function getLastActiveAccount($timeout = VISIT_DATA_TIMEOUT): ?accountModelObj
    {
        $data = $this->getLastActiveData(accountModelObj::class, []);
        if ($data && $data['id'] > 0 && TIMESTAMP - $data['time'] < $timeout) {
            return Account::get($data['id']);
        }

        return null;
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

    public function getRecipientData()
    {
        return $this->get('recipient', []);
    }

    public function updateRecipientData($name, $phone_num, $address): bool
    {
        return $this->set('recipient', [
            'name' => $name,
            'phoneNum' => $phone_num,
            'address' => $address,
        ]);
    }

    public function getCommissionBalanceCard(): ICard
    {
        return new UserCommissionBalanceCard($this);
    }

    public function getVIPCard(): ICard
    {
        return new VIPCard($this);
    }

    public function getPhysicalCardNO(): string
    {
        if (empty($this->s1)) {
            do {
                $s1 = sprintf('%s%s', date('Ymd', $this->createtime), Util::random(4, true));
            } while (User::findOne(['s1' => $s1]));

            $this->setS1($s1);
            $this->save();
        }

        return $this->s1;
    }

    public function getAgentLevel(): array
    {
        $level = $this->getAgentData('level', '');
        if ($level) {
            $data = settings("agent.levels.$level", []);
            $data['level'] = $level;

            return $data;
        }

        return [];
    }

    /**
     * @param string $path
     * @param null $default
     * @return mixed
     */
    public function getAgentData(string $path = '', $default = null)
    {
        if ($this->isAgent()) {
            $key = 'agentData';
            if (!empty($path)) {
                $key .= ".$path";
            }

            return $this->settings($key, $default);
        }

        if ($this->isPartner()) {
            $agent = $this->getPartnerAgent();
            if ($agent) {
                return $agent->getAgentData($path, $default);
            }
        }

        return null;
    }

    public function getCredit(): int
    {
        return $this->settings('credit.val', 0);
    }

    public function setCredit(int $val): bool
    {
        return $this->updateSettings('credit.val', $val);
    }

    public function isBusy(): bool
    {
        if (App::isChargingDeviceEnabled()) {
            if (ChargingNowData::countByUser($this) > 0) {
                return true;
            }
        }

        if (App::isFuelingDeviceEnabled()) {
            $user_fueling_data = $this->fuelingNOWData();
            if ($user_fueling_data) {
                return true;
            }
            if (Fueling::hasUnpaidOrder($this)) {
                return true;
            }
        }

        return false;
    }

    public function setFuelingNOWData($data): bool
    {
        return $this->updateSettings('fuelingNOW', $data);
    }

    public function fuelingNOWData(string $key = '', $default = [])
    {
        $path = 'fuelingNOW';
        if ($key) {
            $path .= ".$key";
        }

        return $this->settings($path, $default);
    }

    public function removeFuelingNOWData(): bool
    {
        return $this->remove('fuelingNOW');
    }

    public function hasFactoryPermission(): bool
    {
        return !empty($this->settings('agentData.misc.power'));
    }

    /**
     * @return referralModelObj
     */
    public function getReferral(): ?referralModelObj
    {
        /** @var referralModelObj $referral */
        $referral = Referral::findOne(['user_id' => $this->getId()]);
        if (empty($referral)) {
            do {
                $code = Util::random(6, true);
            } while (Referral::exists(['code' => $code]));

            $referral = Referral::create(['user_id' => $this->getId(), 'code' => $code]);
        }

        return $referral;
    }

    /**
     * 是否是推广员.
     *
     * @return bool
     */
    public function isPromoter(): bool
    {
        return Principal::is($this, Principal::Promoter);
    }
}
