<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\Agent;
use zovye\model\agentModelObj;
use zovye\model\deviceModelObj;

class App
{
    /**
     * 返回系统唯一uid
     */
    public static function uid(int $len = 0): string
    {
        return onceCall(function () use ($len) {
            $uid = sha1(_W('config.setting.authkey').We7::uniacid());
            if ($len > 0) {
                return substr($uid, 0, $len);
            }

            return $uid;
        }, $len);
    }

    public static function secret(): string
    {
        return _W('config.setting.authkey', '');
    }

    public static function isLocationValidateEnabled(): bool
    {
        return settings('user.location.validate.enabled', false);
    }

    public static function isAdsReviewEnabled(): bool
    {
        return settings('agent.advs.review.enabled') !== 0;
    }

    /**
     * 是否开启虚拟设备支持
     */
    public static function isVDeviceSupported(): bool
    {
        return settings('device.v-device.enabled', false);
    }

    /**
     * 是否开启彩票商品商品支持
     */
    public static function isLotteryGoodsSupported(): bool
    {
        return settings('goods.lottery.enabled', false);
    }

    /**
     * 是否开启计时商品商品支持
     */
    public static function isTsGoodsSupported(): bool
    {
        return settings('goods.ts.enabled', false);
    }

    /**
     * 是否开启实名认证功能
     */
    public static function isIDCardVerifyEnabled(): bool
    {
        return self::isIDCardVerifySupported() && settings('user.verify.enabled', false);
    }

    public static function isIDCardVerifySupported(): bool
    {
        return settings('idcard.verify.enabled', false);
    }

    /**
     * 是否开启蓝牙主板功能
     */
    public static function isBluetoothDeviceSupported(): bool
    {
        return settings('device.bluetooth.enabled', false);
    }

    /**
     * 是否开启取货码功能
     */

    public static function isGoodsVoucherEnabled(): bool
    {
        return settings('goods.voucher.enabled', false);
    }

    /**
     * 是否开启 准粉吧 吸粉
     *
     */
    public static function isJfbEnabled(): bool
    {
        return settings('jfb.fan.enabled', false);
    }

    /**
     * 是否开启 公锤 吸粉
     *
     */
    public static function isMoscaleEnabled(): bool
    {
        return settings('moscale.fan.enabled', false);
    }

    /**
     * 是否开启 云粉吧 吸粉
     *
     */
    public static function isYunfenbaEnabled(): bool
    {
        return settings('yunfenba.fan.enabled', false);
    }

    /**
     * 是否开启 阿旗数据平台 吸粉
     *
     */
    public static function isAQiinfoEnabled(): bool
    {
        return settings('AQiinfo.fan.enabled', false);
    }

    /**
     * 是否开启 纸巾宝 吸粉
     *
     */
    public static function isZJBaoEnabled(): bool
    {
        return settings('zjbao.fan.enabled', false);
    }

    /**
     * 是否开启 美葩 吸粉
     *
     */
    public static function isMeiPaEnabled(): bool
    {
        return settings('meipa.fan.enabled', false);
    }

    /**
     * 是否开启 金粉吧 吸粉
     *
     */
    public static function isKingFansEnabled(): bool
    {
        return settings('king.fan.enabled', false);
    }

    /**
     * 是否开启 史莱姆 吸粉
     *
     */
    public static function isSNTOEnabled(): bool
    {
        return settings('snto.fan.enabled', false);
    }

    /**
     * 是否开启 粉丝宝 吸粉
     *
     */
    public static function isYFBEnabled(): bool
    {
        return settings('yfb.fan.enabled', false);
    }

    /**
     * 是否开启 企业微信接新（阿旗） 吸粉
     *
     */
    public static function isWxWorkEnabled(): bool
    {
        return settings('wxWork.fan.enabled', false);
    }

    /**
     * 是否开启 友粉
     *
     */
    public static function isYouFenEnabled(): bool
    {
        return settings('YouFen.fan.enabled', false);
    }

    /**
     * 是否开启 涨啊
     *
     */
    public static function isMengMoEnabled(): bool
    {
        return settings('MengMo.fan.enabled', false);
    }

    /**
     * 是否开启 壹道
     *
     */
    public static function isYiDaoEnabled(): bool
    {
        return settings('YiDao.fan.enabled', false);
    }

    /**
     * 是否开启 微保
     *
     */
    public static function isWeiSureEnabled(): bool
    {
        return settings('weiSure.fan.enabled', false);
    }

    /**
     * 是否开启 中科
     *
     */
    public static function isCloudFIEnabled(): bool
    {
        return settings('cloudFI.fan.enabled', false);
    }

    public static function isCommissionEnabled(): bool
    {
        return settings('commission.enabled', false);
    }

    public static function isAgentGSPEnabled(): bool
    {
        return settings('agent.reg.rel_gsp.enabled', false);
    }

    public static function isAgentBonusEnabled(): bool
    {
        return settings('agent.reg.bonus.enabled', false);
    }

    public static function isAgentReferralEnabled(): bool
    {
        return settings('agent.reg.referral', false);
    }

    public static function getAgentRegMode(): int
    {
        return settings('agent.reg.mode', Agent::REG_MODE_NORMAL);
    }

    public static function getUserLocationValidateDistance($default = 0): int
    {
        return settings('user.location.validate.distance', $default);
    }

    public static function getAgentDefaultCommissionFeeType(): int
    {
        return settings('agent.reg.commission_fee_type', 0);
    }

    public static function getAgentDefaultCommissionFee(): int
    {
        return settings('agent.reg.commission_fee', 0);
    }

    public static function getAgentDefaultGSP(): array
    {
        return settings('agent.reg.rel_gsp', []);
    }

    public static function getAgentDefaultGSDModeType(): string
    {
        return settings('agent.reg.gsp_mode_type', 'percent');
    }

    public static function getAgentDefaultBonus(): array
    {
        return settings('agent.reg.bonus', []);
    }

    public static function getAgentDefaultFuncs(): array
    {
        return settings('agent.reg.funcs', []);
    }

    public static function getAgentDefaultLevel(): string
    {
        return settings('agent.reg.level', 'level0');
    }

    public static function isDeviceAutoJoin(): bool
    {
        return settings('device.autoJoin', false);
    }

    public static function getDeviceWaitTimeout(): int
    {
        return settings('device.waitTimeout', DEFAULT_DEVICE_WAIT_TIMEOUT);
    }

    public static function isUserVerify18Enabled(): bool
    {
        return settings('user.verify18.enabled', false);
    }

    public static function isWe7CreditEnabled(): bool
    {
        return settings('we7credit.enabled', false);
    }

    /**
     * 出货策略
     * true 表示库存多的货道优先出货：平衡出货
     * false 表示库存少的货道优先出货：顺序出货
     */
    public static function shipmentBalance(deviceModelObj $device = null): bool
    {
        return onceCall(function () use ($device) {
            if ($device) {
                $agent = $device->getAgent();
                if ($agent) {
                    $balanced = $agent->settings('agentData.device.shipment.balanced');
                    if (isset($balanced)) {
                        return !empty($balanced);
                    }
                }
            }

            return !empty(settings('device.shipment.balanced'));
        }, $device ? $device->getId() : '');

    }

    public static function isHttpsWebsite(): bool
    {
        return true;
    }

    /** 用户单次最多购买的商品数量（0表示不限制） */
    public static function getOrderMaxGoodsNum(): int
    {
        $max = settings('order.goods.maxNum', 10);
        return $max > 0 ? $max : 10000;
    }

    public static function getImageProxyURL(): string
    {
        return settings('goods.image.proxy.url', '');
    }

    public static function getImageProxySecretKey(): string
    {
        return settings('goods.image.proxy.secret', '');
    }

    public static function getRemainWarningNum(agentModelObj $agent = null): int
    {
        return onceCall(function () use ($agent) {
            $remainWarning = 0;
            if ($agent) {
                $remainWarning = $agent->settings('agentData.device.remainWarning', 0);
            }

            if ($remainWarning < 1) {
                $remainWarning = settings('device.remainWarning', 1);
            }

            return $remainWarning;
        }, $agent ? $agent->getId() : '');

    }

    public static function isWxPlatformEnabled(): bool
    {
        return settings('account.wx.platform.enabled', false);
    }

    public static function isGoodsPackageEnabled(): bool
    {
        return settings('custom.goodsPackage.enabled', false);
    }

    public static function isMustFollowAccountEnabled(): bool
    {
        return settings('custom.mustFollow.enabled', false);
    }

    public static function isCustomWxAppEnabled(): bool
    {
        return settings('agent.wx.app.enabled', false);
    }

    public static function isUseAccountQRCode(): bool
    {
        return settings('custom.useAccountQRCode.enabled', false);
    }

    public static function isInventoryEnabled(): bool
    {
        return settings('inventory.enabled', false);
    }

    public static function isAccountLogEnabled(): bool
    {
        return settings('account.log.enabled', false);
    }

    public static function isDonatePayEnabled(): bool
    {
        return settings('custom.DonatePay.enabled', false);
    }

    public static function isZeroBonusEnabled(): bool
    {
        return settings('custom.bonus.zero.enabled', false);
    }

    public static function isDouyinEnabled(): bool
    {
        return settings('account.douyin.enabled', false);
    }

    public static function isBalanceEnabled(): bool
    {
        return Config::balance('enabled', false);
    }

    public static function isDeviceWithDoorEnabled(): bool
    {
        return Config::device('door.enabled', false);
    }

    public static function isMultiQRCodesEnabled(): bool
    {
        return false;
    }

    public static function isChargingDeviceEnabled(): bool
    {
        return Config::charging('enabled', false);
    }

    public static function isFuelingDeviceEnabled(): bool
    {
        return Config::fueling('enabled', false);
    }

    public static function isSponsorAdEnabled(): bool
    {
        return Config::app('ad.sponsor.enabled', false);
    }

    public static function isSmsPromoEnabled(): bool
    {
        return settings('custom.smsPromo.enabled', false);
    }

    public static function isTeamEnabled(): bool
    {
        return settings('custom.team.enabled', false);
    }

    public static function isFlashEggEnabled(): bool
    {
        return settings('custom.flashEgg.enabled', false);
    }

    public static function isPromoterEnabled(): bool
    {
        return settings('custom.promoter.enabled', false);
    }

    public static function isGDCVMachineEnabled(): bool
    {
        return settings('custom.GDCVMachine.enabled', false);
    }

    public static function isMultiGoodsItemEnabled(): bool
    {
        return settings('custom.MultiGoodsItem.enabled', false);
    }

    public static function getUserBalanceByMobileEnabled(): bool
    {
        return settings('custom.getUserBalanceByMobile.enabled', false);
    }

    public static function isTKPromotingEnabled(): bool
    {
        return settings('custom.TKPromoting.enabled', false);
    }

    public static function isDeviceScheduleTaskEnabled(): bool
    {
        return Config::device('schedule.enabled', false);
    }

    public static function isGoodsExpireAlertEnabled(): bool
    {
        return Config::app('misc.GoodsExpireAlert.enabled', false);
    }

    public static function isLongPressOrderEnabled(): bool
    {
        return settings('custom.longPressOrder.enabled', false);
    }

    public static function isKeeperCommissionLimitEnabled(): bool
    {
        return settings('custom.keeper.commissionLimit.enabled', false);
    }

    /**
     * 支付和免费订单佣金是否分开设置，为了兼容原有统一配置
     * @return bool
     */
    public static function isKeeperCommissionOrderDistinguishEnabled(): bool
    {
        return settings('custom.keeper.commissionOrderDistinguish.enabled', false);
    }

    public static function isDevicePayConfigEnabled(): bool
    {
        return settings('custom.device.payConfig.enabled', false);
    }

    // 是否启用设备货道二维码
    public static function isDeviceLaneQRCodeEnabled(): bool
    {
        return settings('custom.device.laneQRCode.enabled', false);
    }

    public static function isPuaiEnabled(): bool
    {
        return settings('custom.puai.enabled', false);
    }

    public static function isAllCodeEnabled(): bool
    {
        return settings('custom.allCode.enabled', false);
    }

    public static function isAppOnlineBonusEnabled(): bool
    {
        return settings('custom.appOnlineBonus.enabled', false);
    }

    public static function isDeviceQoeBonusEnabled(): bool
    {
        return settings('custom.deviceQoeBonus.enabled', false);
    }
}
