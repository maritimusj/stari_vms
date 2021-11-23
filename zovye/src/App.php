<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\userModelObj;
use zovye\model\agentModelObj;
use zovye\model\device_typesModelObj;
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
        return onceCall(function() use ($len) {
            $uid = sha1(_W('config.setting.authkey') . We7::uniacid());
            if ($len > 0) {
                return substr($uid, 0, $len);
            }
            return $uid;
        }, $len);
    }

    public static function isLocationValidateEnabled(): bool
    {
        return onceCall(function() {
            return !empty(settings('user.location.validate.enabled'));
        });
    }

    public static function isAdvsReviewEnabled(): bool
    {
        return onceCall(function() {
            return settings('agent.advs.review.enabled') !== 0;
        });        
    }

    /**
     * 是否开启虚拟设备支持
     * @return bool
     */
    public static function isVDeviceSupported(): bool
    {
        return onceCall(function() {
            return !empty(settings('device.v-device.enabled'));
        });        
    }

    /**
     * 是否开启固定货道商品商品支持
     * @return bool
     */
    public static function isLotteryGoodsSupported(): bool
    {
        return onceCall(function() {
            return !empty(settings('goods.lottery.enabled'));
        });        
    }

    /**
     * 是否开启实名认证功能
     * @return bool
     */
    public static function isIDCardVerifyEnabled(): bool
    {
        return onceCall(function() {
            return self::isIDCardVerifySupported() && settings('user.verify.enabled');
        });        
    }

    public static function isIDCardVerifySupported(): bool
    {
        return onceCall(function() {
            return !empty(settings('idcard.verify.enabled'));
        });        
    }

    /**
     * 是否开启蓝牙主板功能
     * @return bool
     */
    public static function isBluetoothDeviceSupported(): bool
    {
        return onceCall(function() {
            return !empty(settings('device.bluetooth.enabled'));
        });        
    }

    /**
     * 是否开启取货码功能
     */

    public static function isGoodsVoucherEnabled(): bool
    {
        return onceCall(function() {
            return !empty(settings('goods.voucher.enabled'));
        });        
    }

    /**
     * 是否开启 准粉吧 吸粉
     *
     */
    public static function isJfbEnabled(): bool
    {
        return onceCall(function() {
            return !empty(settings('jfb.fan.enabled'));
        });        
    }

    /**
     * 是否开启 公锤 吸粉
     *
     */
    public static function isMoscaleEnabled(): bool
    {
        return onceCall(function() {
            return !empty(settings('moscale.fan.enabled'));            
        });
    }

    /**
     * 是否开启 云粉吧 吸粉
     *
     */
    public static function isYunfenbaEnabled(): bool
    {
        return onceCall(function() {
            return !empty(settings('yunfenba.fan.enabled'));            
        });
    }

    /**
     * 是否开启 阿旗数据平台 吸粉
     *
     */
    public static function isAQiinfoEnabled(): bool
    {
        return onceCall(function() {
            return !empty(settings('AQiinfo.fan.enabled'));            
        });
    }

    /**
     * 是否开启 纸巾宝 吸粉
     *
     */
    public static function isZJBaoEnabled(): bool
    {
        return onceCall(function() {
            return !empty(settings('zjbao.fan.enabled'));
        });
    }

    /**
     * 是否开启 美葩 吸粉
     *
     */
    public static function isMeiPaEnabled(): bool
    {
        return onceCall(function() {
            return !empty(settings('meipa.fan.enabled'));
        });
    }

    /**
     * 是否开启 金粉吧 吸粉
     *
     */
    public static function isKingFansEnabled(): bool
    {
        return onceCall(function() {
            return !empty(settings('king.fan.enabled'));
        });
    }

    /**
     * 是否开启 史莱姆 吸粉
     *
     */
    public static function isSNTOEnabled(): bool
    {
        return onceCall(function() {
            return !empty(settings('snto.fan.enabled'));
        });
    }

    /**
     * 是否开启 粉丝宝 吸粉
     *
     */
    public static function isYFBEnabled(): bool
    {
        return onceCall(function() {
            return !empty(settings('yfb.fan.enabled'));
        });
    }

    /**
     * 是否开启 企业微信接新（阿旗） 吸粉
     *
     */
    public static function isWxWorkEnabled(): bool
    {
        return onceCall(function() {
            return !empty(settings('wxWork.fan.enabled'));
        });
    }

    public static function isCommissionEnabled(): bool
    {
        return onceCall(function() {
            return !empty(settings('commission.enabled'));
        });
    }

    public static function isAgentGSPEnabled(): bool
    {
        return onceCall(function() {
            return !empty(settings('agent.reg.rel_gsp.enabled'));
        });
    }

    public static function isAgentBonusEnabled(): bool
    {
        return onceCall(function() {
            return !empty(settings('agent.reg.bonus.enabled'));
        });
    }

    public static function isAgentReferralEnabled(): bool
    {
        return onceCall(function() {
            return !empty(settings('agent.reg.referral'));
        });
    }

    public static function agentRegMode(): int
    {
        return onceCall(function() {
            return settings('agent.reg.mode', Agent::REG_MODE_NORMAL);
        });
    }

    public static function userLocationValidateDistance($default = 0): int
    {
        return onceCall(function() use($default) {
            return intval(settings('user.location.validate.distance', $default));
        }, $default);
    }

    public static function agentDefaultCommissionFeeType(): int
    {
        return onceCall(function() {
            return intval(settings('agent.reg.commission_fee_type'));
        });
    }

    public static function agentDefaultCommissionFee(): int
    {
        return onceCall(function() {
            return intval(settings('agent.reg.commission_fee'));
        });
    }

    public static function agentDefaultGSP(): array
    {
        return onceCall(function() {
            return settings('agent.reg.rel_gsp', []);
        });
    }

    public static function agentDefaultGSDModeType(): string
    {
        return onceCall(function() {
            return settings('agent.reg.gsp_mode_type', 'percent');
        });
    }

    public static function agentDefaultBonus(): array
    {
        return onceCall(function() {
            return settings('agent.reg.bonus', []);
        });
    }

    public static function agentDefaultFuncs(): array
    {
        return onceCall(function() {
            return settings('agent.reg.funcs', []);
        });
    }

    public static function agentDefaultLevel(): string
    {
        return onceCall(function() {
            return settings('agent.reg.level', 'level0');
        });
    }

    public static function deviceAutoJoin(): bool
    {
        return onceCall(function() {
            return !empty(settings('device.autoJoin'));
        });
    }

    public static function deviceWaitTimeout(): int
    {
        return onceCall(function() {
            return intval(settings('device.waitTimeout')) ?: DEFAULT_DEVICE_WAIT_TIMEOUT;
        });
    }

    public static function isUserVerify18Enabled(): bool
    {
        return onceCall(function() {
            return !empty(settings('user.verify18.enabled'));
        });
    }

    public static function we7CreditEnabled(): bool
    {
        return onceCall(function() {
            return !empty(settings('we7credit.enabled'));
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
        return onceCall(function() use ($device) {
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
        return onceCall(function() {
            return intval(settings('order.goods.maxNum')) ?: 10;
        });
    }

    public static function imageProxyURL(): string
    {
        return onceCall(function() {
            return strval(settings('goods.image.proxy.url'));
        });
    }

    public static function imageProxySecretKey(): string
    {
        return onceCall(function() {
            return strval(settings('goods.image.proxy.secret'));
        });
    }

    public static function remainWarningNum(agentModelObj $agent = null): int
    {
        return onceCall(function() use ($agent) {
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
        return onceCall(function() {
            return boolval(settings('account.wx.platform.enabled'));
        });
    }

    public static function isMustFollowAccountEnabled(): bool
    {
        return onceCall(function() {
            return boolval(settings('custom.mustFollow.enabled'));
        });
    }

    public static function setContainer(userModelObj $user)
    {
        if ($user->isAliUser()) {
            $_SESSION['ali_user_id'] = $user->getOpenid();
        } elseif ($user->isWxUser()) {
            $_SESSION['wx_user_id'] = $user->getOpenid();
        } elseif ($user->isDouYinUser()) {
            $_SESSION['douyin_user_id'] = $user->getOpenid();
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
        if (self::isDouYinUser()) {
            return strval($_SESSION['douyin_user_id']);
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

     public static function isDouYinUser(): bool
    {
        return !empty($_SESSION['douyin_user_id']);
    }   

    public static function isChannelPayEnabled(): bool
    {
        return onceCall(function() {
            return boolval(settings('custom.channelPay.enabled'));
        });
    }

    public static function isSQMPayEnabled(): bool
    {
        return onceCall(function() {
            return boolval(settings('custom.SQMPay.enabled'));
        });
    }

    public static function isCustomWxAppEnabled(): bool
    {
        return onceCall(function() {
            return boolval(settings('agent.wx.app.enabled'));
        });
    }

    public static function isCustomAliTicketEnabled(): bool
    {
        return boolval(settings('custom.aliTicket.enabled'));
    }

    public static function getDefaultDeviceType():? device_typesModelObj
    {
        $id = settings('device.multi-types.first');
        if ($id) {
            return DeviceTypes::get($id);
        }
        return null;
    }

    public static function useAccountQRCode(): bool
    {
        return onceCall(function() {
            return boolval(settings('custom.useAccountQRCode.enabled'));
        });
    }

    public static function isInventoryEnabled(): bool {
        return onceCall(function() {
            return boolval(settings('inventory.enabled'));
        });        
    }

    public static function isAccountLogEnabled(): bool {
        return onceCall(function() {
            return boolval(settings('account.log.enabled'));
        });        
    }

    public static function isDonatePayEnabled(): bool {
        return onceCall(function() {
            return boolval(settings('custom.DonatePay.enabled'));
        });        
    }

    /**
     * 使用屏幕推广公众号二维码
     */
    public static function useAccountAppQRCode(): bool {
        return onceCall(function() {
            return boolval(settings('account.appQRCode.enabled'));
        });
    }

    public static function isZeroBonusEnabled(): bool {
        return settings('custom.bonus.zero.enabled', false);
    }

    public static function isDouyinEnabled(): bool {
        return settings('account.douyin.enabled', false);
    }

    public static function isMultiQRCodesEnabled(): bool {
        return false;
    }

    public static function isBalanceEnabled(): bool {
        return Config::balance('enabled', false);
    }
}
