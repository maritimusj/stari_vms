<?php

namespace zovye;

use zovye\model\userModelObj;
use zovye\model\agentModelObj;

class App
{
    /**
     * 返回系统唯一uid
     * @param int $len
     * @return string
     */
    public static function uid($len = null): string
    {
        $uid = sha1(_W('config.setting.authkey') . We7::uniacid());
        if ($len > 0) {
            return substr($uid, 0, $len);
        }

        return $uid;
    }

    public static function isLocationValidateEnabled(): bool
    {
        return !empty(settings('user.location.validate.enabled'));
    }

    /**
     * 是否开启二维码附加用户跟踪数据功能
     * @return bool
     */
    public static function isAdvsQrcodeTrackerSupported(): bool
    {
        return !empty(settings('advs.qrcode_tracker.enabled'));
    }

    /**
     * 是否开启网站吸粉广告
     * @return bool
     */
    public static function isSiteUrlEnabled(): bool
    {
        return !empty(settings('advs.site_url.enabled'));
    }

    public static function isAdvsReviewEnabled(): bool
    {
        return settings('agent.advs.review.enabled') !== 0;
    }

    /**
     * 是否开启虚拟设备支持
     * @return bool
     */
    public static function isVDeviceSupported(): bool
    {
        return !empty(settings('device.v-device.enabled'));
    }

    /**
     * 是否开启固定货道商品商品支持
     * @return bool
     */
    public static function isLotteryGoodsSupported(): bool
    {
        return !empty(settings('goods.lottery.enabled'));
    }

    /**
     * 是否开启实名认证功能
     * @return bool
     */
    public static function isIDCardVerifyEnabled(): bool
    {
        return self::isIDCardVerifySupported() && settings('user.verify.enabled');
    }

    public static function isIDCardVerifySupported(): bool
    {
        return !empty(settings('idcard.verify.enabled'));
    }

    /**
     * 是否开启蓝牙主板功能
     * @return bool
     */
    public static function isBluetoothDeviceSupported(): bool
    {
        return !empty(settings('device.bluetooth.enabled'));
    }

    /**
     * 是否开启取货码功能
     */

    public static function isGoodsVoucherEnabled(): bool
    {
        return !empty(settings('goods.voucher.enabled'));
    }

    /**
     * 是否开启 准粉吧 吸粉
     *
     */
    public static function isJfbEnabled(): bool
    {
        return !empty(settings('jfb.fan.enabled'));
    }

    /**
     * 是否开启 公锤 吸粉
     *
     */
    public static function isMoscaleEnabled(): bool
    {
        return !empty(settings('moscale.fan.enabled'));
    }

    /**
     * 是否开启 云粉吧 吸粉
     *
     */
    public static function isYunfenbaEnabled(): bool
    {
        return !empty(settings('yunfenba.fan.enabled'));
    }

    /**
     * 是否开启 阿旗数据平台 吸粉
     *
     */
    public static function isAQiinfoEnabled(): bool
    {
        return !empty(settings('AQiinfo.fan.enabled'));
    }

    /**
     * 是否启用用户中心
     * @return bool
     */
    public static function isUserCenterEnabled(): bool
    {
        return !empty(settings('user.center.enabled'));
    }

    public static function isUserPrizeEnabled(): bool
    {
        return !empty(settings('user.prize.enabled'));
    }

    public static function maxUserPrizeTimes(): int
    {
        return settings('user.prize.max_times', 10);
    }

    public static function isCommissionEnabled(): bool
    {
        return !empty(settings('commission.enabled'));
    }

    public static function isAgentGSPEnabled(): bool
    {
        return !empty(settings('agent.reg.rel_gsp.enabled'));
    }

    public static function isAgentBonusEnabled(): bool
    {
        return !empty(settings('agent.reg.bonus.enabled'));
    }

    public static function isAgentReferralEnabled(): bool
    {
        return !empty(settings('agent.reg.referral'));
    }

    public static function agentRegMode(): int
    {
        return settings('agent.reg.mode', Agent::REG_MODE_NORMAL);
    }

    public static function userLocationValidateDistance($default = 0): int
    {
        return intval(settings('user.location.validate.distance', $default));
    }

    public static function agentDefaultCommissionFeeType(): int
    {
        return intval(settings('agent.reg.commission_fee_type'));
    }

    public static function agentDefaultCommissionFee(): int
    {
        return intval(settings('agent.reg.commission_fee'));
    }

    public static function agentDefaultGSP(): array
    {
        return settings('agent.reg.rel_gsp', []);
    }

    public static function agentDefaultGSDModeType(): string
    {
        return settings('agent.reg.gsp_mode_type', 'percent');
    }

    public static function agentDefaultBonus(): array
    {
        return settings('agent.reg.bonus', []);
    }

    public static function agentDefaultFuncs(): array
    {
        return settings('agent.reg.funcs', []);
    }

    public static function agentDefaultLevel(): string
    {
        return settings('agent.reg.level', 'level0');
    }

    public static function deviceAutoJoin(): bool
    {
        return !empty(settings('device.autoJoin'));
    }

    public static function deviceWaitTimeout(): int
    {
        return intval(settings('device.waitTimeout')) ?: DEFAULT_DEVICE_WAIT_TIMEOUT;
    }

    public static function isUserVerify18Enabled(): bool
    {
        return !empty(settings('user.verify18.enabled'));
    }

    public static function we7CreditEnabled(): bool
    {
        return !empty(settings('we7credit.enabled'));
    }

    /**
     * 出货策略
     * true 表示库存多的货道优先出货：平衡出货
     * false 表示库存少的货道优先出货：顺序出货
     * @return bool
     */
    public static function shipmentBalance(): bool
    {
        return !empty(settings('device.shipment.balanced'));
    }

    public static function isHttpsWebsite(): bool
    {
        return true;
    }

    public static function orderMaxGoodsNum(): int
    {
        return intval(settings('order.goods.maxNum')) ?: 100;
    }


    public static function imageProxyURL(): string
    {
        return strval(settings('goods.image.proxy.url'));
    }

    public static function imageProxySecretKey(): string
    {
        return strval(settings('goods.image.proxy.secret'));
    }

    public static function remainWarningNum(agentModelObj $agent = null): int
    {
        $remainWarning = 0;
        if ($agent) {
            $remainWarning = intval($agent->settings('agentData.device.remainWarning', 0));
        }

        if ($remainWarning < 1) {
            $remainWarning = intval(settings('device.remainWarning', 1));
        }

        return $remainWarning;
    }

    public static function isWxPlatformEnabled(): bool
    {
        return boolval(settings('account.wx.platform.enabled'));
    }

    public static function isMustFollowAccountEnabled(): bool
    {
        return boolval(settings('custom.mustFollow.enabled'));
    }

    public static function setContainer(userModelObj $user)
    {
        if ($user->isAliUser()) {
            $_SESSION['ali_user_id'] = $user->getOpenid();
        } elseif ($user->isWxUser()) {
            $_SESSION['wx_user_id'] = $user->getOpenid();
        }
    }

    public static function getUserUID(): string
    {
        if (self::isAliUser()) {
            return strval($_SESSION['ali_user_id']);
        }
        if (self::isWxUser()) {
            return strval($_SESSION['wx_user_id']);
        }
        return '';
    }

    public static function isAliUser(): bool
    {
        return !empty($_SESSION['ali_user_id']);
    }

    public static function isWxUser(): bool
    {
        return !empty($_SESSION['wx_user_id']);
    }

    public static function isChannelPayEnabled(): bool
    {
        return boolval(settings('custom.channelPay.enabled'));
    }

    public static function isSQMPayEnabled(): bool
    {
        return boolval(settings('custom.SQMPay.enabled'));
    }

    public static function isCustomWxAppEnabled(): bool
    {
        return boolval(settings('agent.wx.app.enabled'));
    }
}
