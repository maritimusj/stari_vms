<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\api\wx;

use DateTime;
use DateTimeImmutable;
use Exception;
use zovye\Account;
use zovye\App;
use zovye\CommissionBalance;
use zovye\model\accountModelObj;
use zovye\Request;
use zovye\Schema;
use zovye\Stats;
use zovye\User;
use zovye\Util;
use function zovye\err;
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
        $user = common::getAgentOrPartner();

        common::checkCurrentUserPrivileges(['F_cm', 'F_pt']);

        //检查用户是否已同意平台协议
        if (settings('commission.agreement.freq')) {
            $agreement = $user->get('commissionAgreementData', []);
            if (empty($agreement['version']) || $agreement['version'] != settings('commission.agreement.version')) {
                return err('用户必须要先同意平台协议后，才能使用该功能！');
            }
        }

        $page = max(1, Request::int('page'));
        $page_size = max(1, Request::int('pagesize', DEFAULT_PAGE_SIZE));

        $query = Account::query([
            'shared' => 1,
            'state' => 1,
        ]);

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
        $user = common::getAgentOrPartner();

        common::checkCurrentUserPrivileges(['F_cm', 'F_pt']);

        //检查用户是否已同意平台协议
        if (settings('commission.agreement.freq')) {
            $agreement = $user->settings('commissionAgreementData', []);
            if (empty($agreement['version']) || $agreement['version'] != settings('commission.agreement.version')) {
                return err('用户必须要先同意平台协议后，才能使用该功能！');
            }
        }

        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();

        $uid = Request::trim('uid');
        if ($uid) {
            $account = Account::findOneFromUID($uid);

            if (!$account->getShared()) {
                return err('公众号没有加入推广！');
            }

            $assign_data = [$account];
            $params = [];

            if (Request::isset('all')) {
                $assign_data[] = $agent;
                if (!Request::has('all')) {
                    $params['revert'] = true;
                }
            }

            if (Account::bind($assign_data, $params)) {
                return ['msg' => $params['revert'] ? '成功退出' : '成功加入'];
            }
        }

        return err('操作失败！');
    }

    /**
     * 广告联盟，协议.
     *
     * @return array
     */
    public static function ptAgreement(): array
    {
        $user = common::getAgentOrPartner();

        common::checkCurrentUserPrivileges(['F_cm', 'F_pt']);

        $agreement = settings('commission.agreement');
        if (Request::has('acquire')) {
            $userData = $user->settings('commissionAgreementData', []);
            if ($agreement['freq'] && $userData['version'] != $agreement['version']) {
                return [
                    'must' => true,
                    'version' => $agreement['version'],
                    'content' => $agreement['content'],
                ];
            }

            return ['must' => false];
        } elseif (Request::has('attitude')) {
            $version = Request::trim('version');

            if (Request::str('attitude') == 'yes' && $version == $agreement['version']) {
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

        return err('错误请求！');
    }

    public static function level(): array
    {
        common::checkCurrentUserPrivileges('F_cm');

        $guid = Request::trim('guid');
        $val = min(10000, max(0, Request::float('val', 0, 2) * 100));
        $level = min(3, max(0, Request::int('level')));

        $agent = agent::getUserByGUID($guid);
        if (empty($agent)) {
            return err('找不到这个代理商！');
        }

        $gsp = $agent->settings('agentData.gsp');
        if ($gsp['enabled']) {
            if ($gsp['mode'] == 'rel') {
                if ($agent->updateSettings("agentData.gsp.rel.level$level", $val)) {
                    return ['msg' => '设置成功！'];
                } else {
                    return err('保存失败，请与管理员联系！[101]');
                }
            } else {
                return err('设置失败，请与管理员联系！[102]');
            }
        }

        return err('未启用，请与管理员联系！[103]');
    }

    public static function monthStat(): array
    {
        $user = User::get(Request::int('id'));
        if (empty($user)) {
            return err('找不到这个用户！');
        }

        if (Request::has('keeper')) {
            if (!$user->isKeeper()) {
                return err('用户不是运营人员！');
            }
        } else {
            if ($user->isPartner()) {
                $user = $user->getPartnerAgent();
            }
        }

        return ['data' => Stats::getUserCommissionStats($user)];
    }

    public static function chargingMonthStats(): array
    {
        if (!App::isChargingDeviceEnabled()) {
            return err('没有开启这个功能！');
        }

        $user = common::getAgentOrPartner();

        $agent = $user->isPartner() ? $user->getPartnerAgent() : $user;

        $result = [];

        try {
            $month = new DateTimeImmutable(Request::str('month').'-01 00:00');

            $sf = Stats::getDailyStats($agent, CommissionBalance::CHARGING_SERVICE_FEE, $month);
            $ef = Stats::getDailyStats($agent, CommissionBalance::CHARGING_ELECTRIC_FEE, $month);

            foreach ($sf as $i => $total) {
                $result[$i] = [
                    'sf' => $total,
                ];
            }

            foreach ($ef as $i => $total) {
                if ($total == 0 && $result[$i]['sf'] == 0) {
                    unset($result[$i]);
                    continue;
                }
                $result[$i]['ef'] = $total;
            }

        } catch (Exception $e) {
        }

        return $result;
    }

    public static function chargingStats(): array
    {
        if (!App::isChargingDeviceEnabled()) {
            return err('没有开启这个功能！');
        }

        $user = common::getAgentOrPartner();

        $agent = $user->isPartner() ? $user->getPartnerAgent() : $user;

        $balance = $agent->getCommissionBalance();

        $result = [
            'yesterday' => [
                'ef' => 0,
                'sf' => 0,
            ],
            'today' => [
                'ef' => 0,
                'sf' => 0,
            ],
            'month' => [
                'ef' => 0,
                'sf' => 0,
            ],
        ];

        $result['yesterday']['ef'] = (int)$balance->log()->where([
            'src' => CommissionBalance::CHARGING_ELECTRIC_FEE,
            'createtime >=' => (new DateTime('last day 00:00'))->getTimestamp(),
            'createtime <' => (new DateTime('today'))->getTimestamp(),
        ])->sum('x_val');

        $result['yesterday']['sf'] = (int)$balance->log()->where([
            'src' => CommissionBalance::CHARGING_SERVICE_FEE,
            'createtime >=' => (new DateTime('last day 00:00'))->getTimestamp(),
            'createtime <' => (new DateTime('today'))->getTimestamp(),
        ])->sum('x_val');

        $result['today']['ef'] = (int)$balance->log()->where([
            'src' => CommissionBalance::CHARGING_ELECTRIC_FEE,
            'createtime >=' => (new DateTime('today'))->getTimestamp(),
        ])->sum('x_val');

        $result['today']['sf'] = (int)$balance->log()->where([
            'src' => CommissionBalance::CHARGING_SERVICE_FEE,
            'createtime >=' => (new DateTime('today'))->getTimestamp(),
        ])->sum('x_val');

        $result['month']['ef'] = (int)$balance->log()->where([
            'src' => CommissionBalance::CHARGING_ELECTRIC_FEE,
            'createtime >=' => (new DateTime('first day of this month 00:00'))->getTimestamp(),
        ])->sum('x_val');

        $result['month']['sf'] = (int)$balance->log()->where([
            'src' => CommissionBalance::CHARGING_SERVICE_FEE,
            'createtime >=' => (new DateTime('first day of this month 00:00'))->getTimestamp(),
        ])->sum('x_val');


        $sf = Stats::getMonthStats($agent, CommissionBalance::CHARGING_SERVICE_FEE);
        $ef = Stats::getMonthStats($agent, CommissionBalance::CHARGING_ELECTRIC_FEE);

        $result['list'] = [];
        foreach ($sf as $i => $total) {
            $result['list'][$i] = [
                'sf' => $total,
                'ef' => 0,
            ];
        }

        foreach ($ef as $i => $total) {
            $result['list'][$i]['ef'] = $total;
            if (!isset($result['list'][$i]['sf'])) {
                $result['list'][$i]['sf'] = 0;
            }
        }

        return $result;
    }
}