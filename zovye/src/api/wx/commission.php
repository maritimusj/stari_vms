<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\api\wx;

use zovye\Account;
use zovye\model\accountModelObj;
use zovye\request;
use zovye\Schema;
use zovye\State;
use zovye\Stats;
use zovye\User;
use zovye\Util;
use zovye\We7;
use function zovye\err;
use function zovye\error;
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
        $page_size = max(1, request::int('pagesize', DEFAULT_PAGE_SIZE));

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
                if ($agent->updateSettings("agentData.gsp.rel.level$level", $val)) {
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

    public static function monthStat(): array
    {
        $user = User::get(request::int('id'));
        if (empty($user)) {
            return err('找不到这个用户！');
        }

        if (request::has('keeper')) {
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
}