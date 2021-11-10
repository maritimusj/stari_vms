<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\api\wx;

use DateTime;
use zovye\Account;
use zovye\Cache;
use zovye\model\accountModelObj;
use zovye\model\commission_balanceModelObj;
use zovye\CommissionBalance;
use zovye\model\userModelObj;
use zovye\request;
use zovye\Schema;
use zovye\State;
use zovye\User;
use zovye\Util;
use zovye\We7;
use function zovye\err;
use function zovye\error;
use function zovye\is_error;
use function zovye\m;
use function zovye\settings;

class commission
{

    /**
     * 广告联盟，公众号列表.
     *
     * @return array
     */
    public static function sharedAccount(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges(['F_cm', 'F_pt']);

        //检查用户是否已同意平台协议
        if (settings('commission.agreement.freq')) {
            $agreement = $user->get('commissionAgreementData', []);
            if (empty($agreement['version']) || $agreement['version'] != settings('commission.agreement.version')) {
                return error(State::ERROR, '用户必须要先同意平台协议后，才能使用该功能！');
            }
        }

        $page = max(1, request::int('page'));
        $page_size = max(1, request::int('pagesize', DEFAULT_PAGESIZE));

        $query = m('account')->query();
        $query->where(
            We7::uniacid(
                [
                    'shared' => 1,
                    'state' => 1,
                ]
            )
        );

        $total = $query->count();
        $result = [
            'page' => $page,
            'pagesize' => $page_size,
            'total' => $total,
            'totalpage' => ceil($total / $page_size),
            'list' => [],
        ];

        if ($total > 0) {
            $agent = $user->isAgent() ? $user : $user->getPartnerAgent();

            $query->page($page, $page_size);
            $query->orderBy('id desc');

            /** @var accountModelObj $entry */
            foreach ($query->findAll() as $entry) {
                $data = [
                    'uid' => strval($entry->getUid()),
                    'title' => strval($entry->getTitle()),
                    'descr' => strval($entry->getDescription()),
                    'img' => strval(Util::toMedia($entry->getImg())),
                    'qrcode' => strval(Util::toMedia($entry->getQrcode())),
                    'clr' => strval($entry->getClr()),
                    'scname' => Schema::desc($entry->getScname()),
                    'url' => strval($entry->getUrl()),
                ];

                //佣金
                $data['price'] = number_format($entry->settings('commission.money', 0) / 100, 2);

                //是否有分配
                $data['enabled'] = Account::isRelated($entry->settings('assigned', []), $agent);

                $result['list'][] = $data;
            }
        }

        return $result;
    }

    /**
     * 广告联盟，分配公众号.
     *
     * @return array
     */
    public static function accountAssign(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges(['F_cm', 'F_pt']);

        //检查用户是否已同意平台协议
        if (settings('commission.agreement.freq')) {
            $agreement = $user->settings('commissionAgreementData', []);
            if (empty($agreement['version']) || $agreement['version'] != settings('commission.agreement.version')) {
                return error(State::ERROR, '用户必须要先同意平台协议后，才能使用该功能！');
            }
        }

        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();

        $uid = request::trim('uid');
        if ($uid) {
            $account = Account::findOneFromUID($uid);

            if (!$account->getShared()) {
                return error(State::ERROR, '公众号没有加入推广！');
            }

            $assign_data = [$account];
            $params = [];

            if (request::isset('all')) {
                $assign_data[] = $agent;
                if (!request::has('all')) {
                    $params['revert'] = true;
                }
            }

            if (Account::bind($assign_data, $params)) {
                return ['msg' => $params['revert'] ? '成功退出' : '成功加入'];
            }
        }

        return error(State::ERROR, '操作失败！');
    }

    /**
     * 广告联盟，协议.
     *
     * @return array
     */
    public static function ptAgreement(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges(['F_cm', 'F_pt']);

        $agreement = settings('commission.agreement');
        if (request::has('acquire')) {
            $userData = $user->settings('commissionAgreementData', []);
            if ($agreement['freq'] && $userData['version'] != $agreement['version']) {
                return [
                    'must' => true,
                    'version' => $agreement['version'],
                    'content' => $agreement['content'],
                ];
            }

            return ['must' => false];
        } elseif (request::has('attitude')) {
            $version = request::trim('version');

            if (request::str('attitude') == 'yes' && $version == $agreement['version']) {
                $user->updateSettings(
                    'commissionAgreementData',
                    [
                        'datetime' => time(),
                        'version' => $version,
                        'content' => $agreement['content'],
                    ]
                );

                return ['msg' => '已同意！'];
            } else {
                $user->remove('commissionAgreementData');

                return ['msg' => '已拒绝！'];
            }
        }

        return error(State::ERROR, '错误请求！');
    }

    public static function level(): array
    {
        common::checkCurrentUserPrivileges('F_cm');

        $guid = request::trim('guid');
        $val = min(10000, max(0, request::float('val', 0, 2) * 100));
        $level = min(3, max(0, request::int('level')));

        $agent = agent::getUserByGUID($guid);
        if (empty($agent)) {
            return error(State::ERROR, '找不到这个代理商！');
        }

        $gsp = $agent->settings('agentData.gsp');
        if ($gsp['enabled']) {
            if ($gsp['mode'] == 'rel') {
                if ($agent->updateSettings("agentData.gsp.rel.level{$level}", $val)) {
                    return ['msg' => '设置成功！'];
                } else {
                    return error(State::ERROR, '保存失败，请与管理员联系！[101]');
                }
            } else {
                return error(State::ERROR, '设置失败，请与管理员联系！[102]');
            }
        }

        return error(State::ERROR, '未启用，请与管理员联系！[103]');
    }

    protected static function getMonthStatsData(userModelObj $user, $begin, $end): array
    {

        $res = CommissionBalance::query([
            'openid' => $user->getOpenid(),
            'createtime >=' => $begin,
            'createtime <' => $end,
        ])->findAll();

        $c_arr = [
            CommissionBalance::ORDER_FREE,
            CommissionBalance::ORDER_BALANCE,
            CommissionBalance::ORDER_WX_PAY,
            //CommissionBalance::REFUND,
            CommissionBalance::ORDER_REFUND,
            CommissionBalance::GSP,
            CommissionBalance::BONUS,
        ];

        $data = [];
        /** @var commission_balanceModelObj $item */
        foreach ($res as $item) {
            $month_date = date('Y-m', $item->getCreatetime());
            if (!isset($data[$month_date])) {
                $data[$month_date]['income'] = 0;
                $data[$month_date]['withdraw'] = 0;
                $data[$month_date]['fee'] = 0;
            }

            $src = $item->getSrc();
            $x_val = $item->getXVal();

            if (in_array($src, $c_arr)) {
                $data[$month_date]['income'] += $x_val;
            } elseif ($src == CommissionBalance::ADJUST) {
                if ($x_val > 0) {
                    $data[$month_date]['income'] += $x_val;
                } else {
                    $data[$month_date]['withdraw'] += $x_val;
                }
            } elseif ($src == CommissionBalance::WITHDRAW) {
                $data[$month_date]['withdraw'] += $x_val;
            } elseif ($src == CommissionBalance::FEE) {
                $data[$month_date]['fee'] += $x_val;
            }
        }
        return $data;
    }

    public static function monthStat(): array
    {
        $user = User::get(request::int('id'));
        if (empty($user)) {
            return err('找不到这个用户！');
        }

        if ($user->isPartner()) {
            $user = $user->getPartnerAgent();
        }

        $firstCommissionBalance = CommissionBalance::getFirstCommissionBalance($user);
        if (empty($firstCommissionBalance)) {
            $data['data'] = [];
            return $data;
        }

        $begin = new DateTime("@{$firstCommissionBalance->getCreatetime()}");
        $end = new DateTime();

        $data = [];

        for (; $begin <= $end;) {
            $month = $begin->format('Y-m');
            $ts = $begin->getTimestamp();

            $uid = Cache::makeUID([
                'api' => 'monthStats',
                'user' => $user->getOpenid(),
                'month' => $month,
            ]);

            $params = [];

            if ($month == date('Y-m')) {
                $params[] = Cache::ResultExpiredAfter(10);
            }

            $begin->modify('first day of next month 00:00');

            $res = Cache::fetch($uid, function () use ($user, $ts, $begin) {
                return self::getMonthStatsData($user, $ts, $begin->getTimestamp());
            }, ...$params);

            if (is_error($res)) {
                return $res;
            }
            
            $data = array_merge($data, $res);
        }

        ksort($data);

        $last_month_balance = 0;
        foreach ($data as $key => $item) {
            $data[$key]['balance'] = $item['income'] + $item['withdraw'] + $item['fee'] + $last_month_balance;
            $last_month_balance = $data[$key]['balance'];
        }

        krsort($data);

        return ['data' => $data];
    }
}