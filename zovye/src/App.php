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
     * @param int $len
     * @return string
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
        return onceCall(function () {
            return settings('user.location.validate.enabled', false);
        });
    }

    public static function isAdsReviewEnabled(): bool
    {
        return onceCall(function () {
            return settings('agent.advs.review.enabled') !== 0;
        });
    }

    /**
     * 是否开启虚拟设备支持
     * @return bool
     */
    public static function isVDeviceSupported(): bool
    {
        return onceCall(function () {
            return settings('device.v-device.enabled', false);
        });
    }

    /**
     * 是否开启固定货道商品商品支持
     * @return bool
     */
    public static function isLotteryGoodsSupported(): bool
    {
        return onceCall(function () {
            return settings('goods.lottery.enabled', false);
        });
    }

    /**
     * 是否开启实名认证功能
     * @return bool
     */
    public static function isIDCardVerifyEnabled(): bool
    {
        return onceCall(function () {
            return self::isIDCardVerifySupported() && settings('user.verify.enabled', false);
        });
    }

    public static function isIDCardVerifySupported(): bool
    {
        return onceCall(function () {
            return settings('idcard.verify.enabled', false);
        });
    }

    /**
     * 是否开启蓝牙主板功能
     * @return bool
     */
    public static function isBluetoothDeviceSupported(): bool
    {
        return onceCall(function () {
            return settings('device.bluetooth.enabled', false);
        });
    }

    /**
     * 是否开启取货码功能
     */

    public static function isGoodsVoucherEnabled(): bool
    {
        return onceCall(function () {
            return settings('goods.voucher.enabled', false);
        });
    }

    /**
     * 是否开启 准粉吧 吸粉
     *
     */
    public static function isJfbEnabled(): bool
    {
        return onceCall(function () {
            return settings('jfb.fan.enabled', false);
        });
    }

    /**
     * 是否开启 公锤 吸粉
     *
     */
    public static function isMoscaleEnabled(): bool
    {
        return onceCall(function () {
            return settings('moscale.fan.enabled', false);
        });
    }

    /**
     * 是否开启 云粉吧 吸粉
     *
     */
    public static function isYunfenbaEnabled(): bool
    {
        return onceCall(function () {
            return settings('yunfenba.fan.enabled', false);
        });
    }

    /**
     * 是否开启 阿旗数据平台 吸粉
     *
     */
    public static function isAQiinfoEnabled(): bool
    {
        return onceCall(function () {
            return settings('AQiinfo.fan.enabled', false);
        });
    }

    /**
     * 是否开启 纸巾宝 吸粉
     *
     */
    public static function isZJBaoEnabled(): bool
    {
        return onceCall(function () {
            return settings('zjbao.fan.enabled', false);
        });
    }

    /**
     * 是否开启 美葩 吸粉
     *
     */
    public static function isMeiPaEnabled(): bool
    {
        return onceCall(function () {
            return settings('meipa.fan.enabled', false);
        });
    }

    /**
     * 是否开启 金粉吧 吸粉
     *
     */
    public static function isKingFansEnabled(): bool
    {
        return onceCall(function () {
            return settings('king.fan.enabled', false);
        });
    }

    /**
     * 是否开启 史莱姆 吸粉
     *
     */
    public static function isSNTOEnabled(): bool
    {
        return onceCall(function () {
            return settings('snto.fan.enabled', false);
        });
    }

    /**
     * 是否开启 粉丝宝 吸粉
     *
     */
    public static function isYFBEnabled(): bool
    {
        return onceCall(function () {
            return settings('yfb.fan.enabled', false);
        });
    }

    /**
     * 是否开启 企业微信接新（阿旗） 吸粉
     *
     */
    public static function isWxWorkEnabled(): bool
    {
        return onceCall(function () {
            return settings('wxWork.fan.enabled', false);
        });
    }

    /**
     * 是否开启 友粉
     *
     */
    public static function isYouFenEnabled(): bool
    {
        return onceCall(function () {
            return settings('YouFen.fan.enabled', false);
        });
    }

    /**
     * 是否开启 涨啊
     *
     */
    public static function isMengMoEnabled(): bool
    {
        return onceCall(function () {
            return settings('MengMo.fan.enabled', false);
        });
    }

    /**
     * 是否开启 壹道
     *
     */
    public static function isYiDaoEnabled(): bool
    {
        return onceCall(function () {
            return settings('YiDao.fan.enabled', false);
        });
    }

    /**
     * 是否开启 微保
     *
     */
    public static function isWeiSureEnabled(): bool
    {
        return onceCall(function () {
            return settings('weiSure.fan.enabled', false);
        });
    }

    /**
     * 是否开启 中科
     *
     */
    public static function isCloudFIEnabled(): bool
    {
        return onceCall(function () {
            return settings('cloudFI.fan.enabled', false);
        });
    }

    public static function isCommissionEnabled(): bool
    {
        return onceCall(function () {
            return settings('commission.enabled', false);
        });
    }

    public static function isAgentGSPEnabled(): bool
    {
        return onceCall(function () {
            return settings('agent.reg.rel_gsp.enabled', false);
        });
    }

    public static function isAgentBonusEnabled(): bool
    {
        return onceCall(function () {
            return settings('agent.reg.bonus.enabled', false);
        });
    }

    public static function isAgentReferralEnabled(): bool
    {
        return onceCall(function () {
            return settings('agent.reg.referral', false);
        });
    }

    public static function getAgentRegMode(): int
    {
        return onceCall(function () {
            return settings('agent.reg.mode', Agent::REG_MODE_NORMAL);
        });
    }

    public static function getUserLocationValidateDistance($default = 0): int
    {
        return onceCall(function () use ($default) {
            return settings('user.location.validate.distance', $default);
        }, $default);
    }

    public static function getAgentDefaultCommissionFeeType(): int
    {
        return onceCall(function () {
            return settings('agent.reg.commission_fee_type', 0);
        });
    }

    public static function getAgentDefaultCommissionFee(): int
    {
        return onceCall(function () {
            return settings('agent.reg.commission_fee', 0);
        });
    }

    public static function getAgentDefaultGSP(): array
    {
        return onceCall(function () {
            return settings('agent.reg.rel_gsp', []);
        });
    }

    public static function getAgentDefaultGSDModeType(): string
    {
        return onceCall(function () {
            return settings('agent.reg.gsp_mode_type', 'percent');
        });
    }

    public static function getAgentDefaultBonus(): array
    {
        return onceCall(function () {
            return settings('agent.reg.bonus', []);
        });
    }

    public static function getAgentDefaultFuncs(): array
    {
        return onceCall(function () {
            return settings('agent.reg.funcs', []);
        });
    }

    public static function getAgentDefaultLevel(): string
    {
        return onceCall(function () {
            return settings('agent.reg.level', 'level0');
        });
    }

    public static function isDeviceAutoJoin(): bool
    {
        return onceCall(function () {
            return settings('device.autoJoin', false);
        });
    }

    public static function getDeviceWaitTimeout(): int
    {
        return onceCall(function () {
            return settings('device.waitTimeout', DEFAULT_DEVICE_WAIT_TIMEOUT);
        });
    }

    public static function isUserVerify18Enabled(): bool
    {
        return onceCall(function () {
            return settings('user.verify18.enabled', false);
        });
    }

    public static function isWe7CreditEnabled(): bool
    {
        return onceCall(function () {
            return settings('we7credit.enabled', false);
        });
    }

    /**
     * 出货策略
     * true 表示库存多的货道优先出货：平衡出货
     * false 表示库存少的货道优先出货：顺序出货
     * @param deviceModelObj|null $device
     * @return bool
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

    public static function getOrderMaxGoodsNum(): int
    {
        return onceCall(function () {
            return settings('order.goods.maxNum', 10);
        });
    }

    public static function getImageProxyURL(): string
    {
        return onceCall(function () {
            return settings('goods.image.proxy.url', '');
        });
    }

    public static function getImageProxySecretKey(): string
    {
        return onceCall(function () {
            return settings('goods.image.proxy.secret', '');
        });
    }

    public static function getRemainWarningNum(agentModelObj $agent = null): int
    {
        return onceCall(function () use ($agent) {
            $remainWarning = 0;
            if ($agent) {
                $remainWarning = intval($agent->settings('agentData.device.remainWarning', 0));
            }

            if ($remainWarning < 1) {
                $remainWarning = intval(settings('device.remainWarning', 1));
            }

            return $remainWarning;
        }, $agent ? $agent->getId() : '');

    }

    public static function isWxPlatformEnabled(): bool
    {
        return onceCall(function () {
            return settings('account.wx.platform.enabled', false);
        });
    }

    public static function isGoodsPackageEnabled(): bool
    {
        return onceCall(function () {
            return settings('custom.goodsPackage.enabled', false);
        });
    }

    public static function isMustFollowAccountEnabled(): bool
    {
        return onceCall(function () {
            return settings('custom.mustFollow.enabled', false);
        });
    }

    public static function isCustomWxAppEnabled(): bool
    {
        return onceCall(function () {
            return settings('agent.wx.app.enabled', false);
        });
    }

    public static function isUseAccountQRCode(): bool
    {
        return onceCall(function () {
            return settings('custom.useAccountQRCode.enabled', false);
        });
    }

    public static function isInventoryEnabled(): bool
    {
        return onceCall(function () {
            return settings('inventory.enabled', false);
        });
    }

    public static function isAccountLogEnabled(): bool
    {
        return onceCall(function () {
            return settings('account.log.enabled', false);
        });
    }

    public static function isDonatePayEnabled(): bool
    {
        return onceCall(function () {
            return settings('custom.DonatePay.enabled', false);
        });
    }

    public static function isZeroBonusEnabled(): bool
    {
        return onceCall(function () {
            return settings('custom.bonus.zero.enabled', false);
        });
    }

    public static function isDouyinEnabled(): bool
    {
        return settings('account.douyin.enabled', false);
    }

    public static function isBalanceEnabled(): bool
    {
        return onceCall(function () {
            return Config::balance('enabled', false);
        });
    }

    public static function isDeviceWithDoorEnabled(): bool
    {
        return onceCall(function () {
            return Config::device('door.enabled', false);
        });
    }

    public static function isMultiQRCodesEnabled(): bool
    {
        return false;
    }

    public static function isChargingDeviceEnabled(): bool
    {
        return onceCall(function () {
            return Config::charging('enabled', false);
        });
    }

    public static function isFuelingDeviceEnabled(): bool
    {
        return onceCall(function () {
            return Config::fueling('enabled', false);
        });
    }

    public static function isSponsorAdEnabled(): bool
    {
        return Config::app('ad.sponsor.enabled', false);
    }

    public static function isSmsPromoEnabled(): bool
    {
        return onceCall(function () {
            return settings('custom.smsPromo.enabled', false);
        });
    }

    public static function isTeamEnabled(): bool
    {
        return onceCall(function () {
            return settings('custom.team.enabled', false);
        });
    }

    public static function isCZTVEnabled(): bool
    {
        return onceCall(function () {
            return settings('custom.cztv.enabled', false);
        });
    }

    public static function isFlashEggEnabled(): bool
    {
        return onceCall(function () {
            return settings('custom.flashEgg.enabled', false);
        });
    }

    public static function isPromoterEnabled(): bool
    {
        return onceCall(function () {
            return settings('custom.promoter.enabled', false);
        });
    }

    public static function isGDCVMachineEnabled(): bool
    {
        return onceCall(function () {
            return settings('custom.GDCVMachine.enabled', false);
        });
    }

    public static function isMultiGoodsItemEnabled(): bool
    {
        return onceCall(function () {
            return settings('custom.MultiGoodsItem.enabled', false);
        });
    }

    public static function getUserBalanceByMobileEnabled(): bool
    {
        return onceCall(function () {
            return settings('custom.getUserBalanceByMobile.enabled', false);
        });
    }

    public static function isTKPromotingEnabled(): bool
    {
        return onceCall(function () {
            return settings('custom.TKPromoting.enabled', false);
        });
    }

    public static function isDeviceScheduleTaskEnabled(): bool
    {
        return onceCall(function () {
            return Config::device('schedule.enabled', false);
        });
    }

    public static function isGoodsExpireAlertEnabled(): bool
    {
        return onceCall(function () {
            return Config::app('misc.GoodsExpireAlert.enabled', false);
        });
    }

    public static function isLongPressOrderEnabled(): bool
    {
        return onceCall(function () {
            return settings('custom.longPressOrder.enabled', false);
        });
    }
}
