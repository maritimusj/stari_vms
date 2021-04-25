<?php

namespace zovye;

use zovye\model\userModelObj;
use zovye\model\agentModelObj;
use zovye\model\deviceModelObj;

class App
{
    /**
     * 返回系统唯一uid
     * @param int $len
     * @return string
     */
    public static function uid($len = null): string
    {
        return __once(function() use ($len) {
            $uid = sha1(_W('config.setting.authkey') . We7::uniacid());
            if ($len > 0) {
                return substr($uid, 0, $len);
            }
        }, $len);
    }

    public static function isLocationValidateEnabled(): bool
    {
        return __once(function() {
            return !empty(settings('user.location.validate.enabled'));
        });
    }

    /**
     * 是否开启二维码附加用户跟踪数据功能
     * @return bool
     */
    public static function isAdvsQrcodeTrackerSupported(): bool
    {
        return __once(function() {
            return !empty(settings('advs.qrcode_tracker.enabled'));
        });        
    }

    /**
     * 是否开启网站吸粉广告
     * @return bool
     */
    public static function isSiteUrlEnabled(): bool
    {
        return __once(function() {
            return !empty(settings('advs.site_url.enabled'));
        });        
    }

    public static function isAdvsReviewEnabled(): bool
    {
        return __once(function() {
            return settings('agent.advs.review.enabled') !== 0;
        });        
    }

    /**
     * 是否开启虚拟设备支持
     * @return bool
     */
    public static function isVDeviceSupported(): bool
    {
        return __once(function() {
            return !empty(settings('device.v-device.enabled'));
        });        
    }

    /**
     * 是否开启固定货道商品商品支持
     * @return bool
     */
    public static function isLotteryGoodsSupported(): bool
    {
        return __once(function() {
            return !empty(settings('goods.lottery.enabled'));
        });        
    }

    /**
     * 是否开启实名认证功能
     * @return bool
     */
    public static function isIDCardVerifyEnabled(): bool
    {
        return __once(function() {
            return self::isIDCardVerifySupported() && settings('user.verify.enabled');
        });        
    }

    public static function isIDCardVerifySupported(): bool
    {
        return __once(function() {
            return !empty(settings('idcard.verify.enabled'));
        });        
    }

    /**
     * 是否开启蓝牙主板功能
     * @return bool
     */
    public static function isBluetoothDeviceSupported(): bool
    {
        return __once(function() {
            return !empty(settings('device.bluetooth.enabled'));
        });        
    }

    /**
     * 是否开启取货码功能
     */

    public static function isGoodsVoucherEnabled(): bool
    {
        return __once(function() {
            return !empty(settings('goods.voucher.enabled'));
        });        
    }

    /**
     * 是否开启 准粉吧 吸粉
     *
     */
    public static function isJfbEnabled(): bool
    {
        return __once(function() {
            return !empty(settings('jfb.fan.enabled'));
        });        
    }

    /**
     * 是否开启 公锤 吸粉
     *
     */
    public static function isMoscaleEnabled(): bool
    {
        return __once(function() {
            return !empty(settings('moscale.fan.enabled'));            
        });
    }

    /**
     * 是否开启 云粉吧 吸粉
     *
     */
    public static function isYunfenbaEnabled(): bool
    {
        return __once(function() {
            return !empty(settings('yunfenba.fan.enabled'));            
        });
    }

    /**
     * 是否开启 阿旗数据平台 吸粉
     *
     */
    public static function isAQiinfoEnabled(): bool
    {
        return __once(function() {
            return !empty(settings('AQiinfo.fan.enabled'));            
        });
    }

    /**
     * 是否启用用户中心
     * @return bool
     */
    public static function isUserCenterEnabled(): bool
    {
        return __once(function() {
            return !empty(settings('user.center.enabled'));
        });
    }

    public static function isUserPrizeEnabled(): bool
    {
        return __once(function() {
            return !empty(settings('user.prize.enabled'));            
        });
    }

    public static function maxUserPrizeTimes(): int
    {
        return __once(function() {
            return settings('user.prize.max_times', 10);            
        });
    }

    public static function isCommissionEnabled(): bool
    {
        return __once(function() {            
            return !empty(settings('commission.enabled'));
        });
    }

    public static function isAgentGSPEnabled(): bool
    {
        return __once(function() {            
            return !empty(settings('agent.reg.rel_gsp.enabled'));
        });
    }

    public static function isAgentBonusEnabled(): bool
    {
        return __once(function() {            
            return !empty(settings('agent.reg.bonus.enabled'));
        });
    }

    public static function isAgentReferralEnabled(): bool
    {
        return __once(function() {            
            return !empty(settings('agent.reg.referral'));
        });
    }

    public static function agentRegMode(): int
    {
        return __once(function() {            
            return settings('agent.reg.mode', Agent::REG_MODE_NORMAL);
        });
    }

    public static function userLocationValidateDistance($default = 0): int
    {
        return __once(function() use($default) {            
            return intval(settings('user.location.validate.distance', $default));
        }, $default);
    }

    public static function agentDefaultCommissionFeeType(): int
    {
        return __once(function() {            
            return intval(settings('agent.reg.commission_fee_type'));
        });
    }

    public static function agentDefaultCommissionFee(): int
    {
        return __once(function() {            
            return intval(settings('agent.reg.commission_fee'));
        });
    }

    public static function agentDefaultGSP(): array
    {
        return __once(function() {            
            return settings('agent.reg.rel_gsp', []);
        });
    }

    public static function agentDefaultGSDModeType(): string
    {
        return __once(function() {            
            return settings('agent.reg.gsp_mode_type', 'percent');
        });
    }

    public static function agentDefaultBonus(): array
    {
        return __once(function() {            
            return settings('agent.reg.bonus', []);
        });
    }

    public static function agentDefaultFuncs(): array
    {
        return __once(function() {            
            return settings('agent.reg.funcs', []);
        });
    }

    public static function agentDefaultLevel(): string
    {
        return __once(function() {            
            return settings('agent.reg.level', 'level0');
        });
    }

    public static function deviceAutoJoin(): bool
    {
        return __once(function() {            
            return !empty(settings('device.autoJoin'));
        });
    }

    public static function deviceWaitTimeout(): int
    {
        return __once(function() {            
            return intval(settings('device.waitTimeout')) ?: DEFAULT_DEVICE_WAIT_TIMEOUT;
        });
    }

    public static function isUserVerify18Enabled(): bool
    {
        return __once(function() {            
            return !empty(settings('user.verify18.enabled'));
        });
    }

    public static function we7CreditEnabled(): bool
    {
        return __once(function() {            
            return !empty(settings('we7credit.enabled'));
        });
    }

    /**
     * 出货策略
     * true 表示库存多的货道优先出货：平衡出货
     * false 表示库存少的货道优先出货：顺序出货
     * @return bool
     */
    public static function shipmentBalance(deviceModelObj $device = null): bool
    {
        return __once(function() use ($device) {    
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

    public static function orderMaxGoodsNum(): int
    {
        return __once(function() {            
            return intval(settings('order.goods.maxNum')) ?: 100;
        });
    }


    public static function imageProxyURL(): string
    {
        return __once(function() {            
            return strval(settings('goods.image.proxy.url'));
        });
    }

    public static function imageProxySecretKey(): string
    {
        return __once(function() {            
            return strval(settings('goods.image.proxy.secret'));
        });
    }

    public static function remainWarningNum(agentModelObj $agent = null): int
    {
        return __once(function() use ($agent) {  
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
        return __once(function() {            
            return boolval(settings('account.wx.platform.enabled'));
        });
    }

    public static function isMustFollowAccountEnabled(): bool
    {
        return __once(function() {            
            return boolval(settings('custom.mustFollow.enabled'));
        });
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
        return __once(function() {            
            return boolval(settings('custom.channelPay.enabled'));
        });
    }

    public static function isSQMPayEnabled(): bool
    {
        return __once(function() {            
            return boolval(settings('custom.SQMPay.enabled'));
        });
    }

    public static function isCustomWxAppEnabled(): bool
    {
        return __once(function() {            
            return boolval(settings('agent.wx.app.enabled'));
        });
    }
}
