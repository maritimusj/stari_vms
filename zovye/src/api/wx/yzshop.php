<?php


namespace zovye\api\wx;

use zovye\State;
use zovye\Stats;
use function zovye\error;
use function zovye\settings;

class yzshop
{
    /**
     * @return array
     */
    public static function stats(): array
    {
        $user = common::getAgent();

        $result = [];

        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();

        if ($agent && settings('agent.yzshop.goods_limits.enabled')) {
            $result['stats'] = Stats::total($agent);

            if (\zovye\YZShop::isInstalled()) {
                $superior = \zovye\YZShop::getSuperior($agent);

                if ($superior) {
                    $result['superior'] = [
                        'name' => $superior->getName(),
                        'avatar' => $superior->getAvatar(),
                        'level' => [
                            'title' => $superior->settings('agentData.level.title', ''),
                            'clr' => $superior->settings('agentData.level.clr', '#CCC'),
                        ],
                    ];
                }

                $goods_limits = settings('agent.yzshop.goods_limits', []);
                if ($goods_limits['id']) {
                    $result['goods'] = \zovye\YZShop::getGoodsInfo($agent, intval($goods_limits['id']));
                    if ($result['goods']['id']) {
                        $result['goods']['order_total'] *= $goods_limits['OR'];
                    }
                }
            }

            if ($result) {
                return $result;
            }
        }

        return error(State::ERROR, '请求失败！');
    }

    /**
     * @return array
     */
    public static function news(): array
    {
        $user = common::getAgent();

        $result = '';

        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();
        if ($agent) {
            if (settings('agent.yzshop.goods_limits.enabled') && \zovye\YZShop::isInstalled()) {
                $goods_limits = settings('agent.yzshop.goods_limits', []);

                if ($goods_limits['id']) {
                    $goodsInfo = \zovye\YZShop::getGoodsInfo($agent, intval($goods_limits['id']));

                    if ($goodsInfo['id']) {
                        $stats = Stats::total($agent);
                        $total = max(0, $goodsInfo['order_total'] * $goods_limits['OR'] - $stats['total']);

                        $title = settings('agent.yzshop.goods_limits.title', '佣金商品剩余：{num}{unit}');
                        $format_str = str_replace(['{num}', '{unit}'], ['%d', '%s'], $title, $count);
                        if ($count < 1) {
                            $format_str .= '%d%s';
                        }

                        $result = sprintf($format_str, $total, $goodsInfo['sku']);
                    }
                }
            }
        }

        return ['news' => $result];
    }
}