<?php
/**
 * 商城插件
 *
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use we7\ihttp;
use zovye\model\userModelObj;

class YZShop
{
    const TB_MEMBER = 'yz_member';
    const TB_GOODS = 'yz_goods';
    const TB_ORDER = 'yz_order';
    const TB_ORDER_GOODS = 'yz_order_goods';

    const ERROR = -1;

    /**
     * @return boolean
     */
    public static function isInstalled(): bool
    {
        return We7::pdo_tableexists(self::TB_MEMBER) && We7::pdo_tableexists(self::TB_GOODS);
    }

    /**
     * @param userModelObj $user
     * @param userModelObj|null $agent
     * @return bool
     */
    public static function create(userModelObj $user, userModelObj $agent = null): bool
    {
        We7::load()->model('mc');

        $uid = We7::mc_openid2uid($user->getOpenid());
        if (empty($uid)) {
            return false;
        }

        $mid = intval(isset($agent) ? We7::mc_openid2uid($agent->getOpenid()) : 0);

        $url = _W('siteroot') . "/addons/yun_shop/api.php?i=" . We7::uniacid() . "&uid=$uid&mid=$mid&type=5&route=member.member.memberFromHXQModule";

        $res = ihttp::get($url);
        if (is_error($res)) {
            Log::error('yzshop', [
                'request_url' => $url,
                'error' => $res,
            ]);

            return false;
        } else {
            $resp = json_decode($res['content'], true);
            if ($resp && $resp['result'] == 1) {
                return true;
            }

            Log::debug('yzshop', [
                'request_url' => $url,
                'response' => $res,
            ]);
        }

        return false;
    }

    /**
     * 获取商城关系链中的上级信息
     * @param userModelObj $user
     * @return mixed
     */
    public static function getSuperior(userModelObj $user)
    {
        if (We7::pdo_tableexists(self::TB_MEMBER)) {
            $res = We7::pdo_get(self::TB_MEMBER, We7::uniacid(['yz_openid' => $user->getOpenid()]), ['parent_id']);
            if ($res && $res['parent_id']) {
                $res = We7::pdo_get(self::TB_MEMBER, We7::uniacid(['member_id' => $res['parent_id']]), ['yz_openid']);
                if ($res && $res['yz_openid']) {
                    return Agent::get($res['yz_openid'], true);
                }
            }
        }

        return null;
    }

    /**
     * 获取商城商品列表
     * @param string $keywords
     * @return array
     */
    public static function getGoodsList($keywords = ''): array
    {
        $condition = [
            'is_deleted' => 0,
        ];
        if ($keywords) {
            $condition['title LIKE'] = "%$keywords%";
        }

        $res = We7::pdo_getall(self::TB_GOODS, We7::uniacid($condition), ['id', 'title']);
        if ($res && is_array($res)) {
            return $res;
        }

        return [];
    }

    /**
     * 获取指定用户佣金限定商品数量
     * @param userModelObj $user
     * @return int
     */
    public static function getRestrictGoodsTotal(userModelObj $user): int
    {
        $goods_id = intval(settings('agent.yzshop.goods_limits.id'));

        if ($goods_id) {
            $res = self::getGoodsInfo($user, $goods_id);
            if ($res) {
                return intval($res['order_total'] * settings('agent.yzshop.goods_limits.OR'));
            }
        }

        return self::ERROR;
    }

    /**
     * 获取指定商品详情
     * @param userModelObj $user
     * @param int $goods_id
     * @return array
     */
    public static function getGoodsInfo(userModelObj $user, int $goods_id): array
    {
        $result = [];
        if ($goods_id > 0) {
            $goods = We7::pdo_get(self::TB_GOODS, We7::uniacid(['id' => $goods_id]));

            if ($goods) {
                $result['id'] = intval($goods['id']);
                $result['title'] = $goods['title'];
                $result['sku'] = $goods['sku'];
                $result['stock'] = intval($goods['stock']);
                $result['thumb'] = $goods['thumb'];
            }

            if ($user) {
                $res = We7::pdo_get(
                    self::TB_MEMBER,
                    We7::uniacid(['yz_openid' => $user->getOpenid()]),
                    ['member_id']
                );
                if ($res && $res['member_id']) {
                    $status = settings('agent.yzshop.goods_limits.order_status', []);
                    if ($status) {
                        $sql = 'SELECT SUM(g.total) AS total FROM ' . We7::tablename(
                                self::TB_ORDER_GOODS
                            ) . ' g LEFT JOIN ' . We7::tablename(
                                self::TB_ORDER
                            ) . ' o ON g.order_id=o.id WHERE g.goods_id=:goods_id AND g.uid=:uid AND o.is_deleted=0 AND o.status IN (' . implode(
                                ',',
                                $status
                            ) . ')';

                        $res = We7::pdo_fetch($sql, [':goods_id' => $goods_id, ':uid' => $res['member_id']]);
                        if ($res) {
                            $result['order_total'] = intval($res['total']);
                        }
                    }
                }
            }
        }

        return $result;
    }
}
