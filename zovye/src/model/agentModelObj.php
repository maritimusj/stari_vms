<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\model;

use zovye\GSP;
use zovye\State;
use zovye\We7;
use zovye\User;
use zovye\Util;
use zovye\Device;

use function zovye\error;
use function zovye\m;
use function zovye\settings;

/**
 * Class agentModelObj
 * @package zovye
 */
class agentModelObj extends userModelObj
{
    public function agentData($path, $default = null)
    {
        return $this->settings("agentData.{$path}", $default);
    }

    public function getName(): string
    {
        $name = $this->settings('agentData.name', '');
        return empty($name) ? parent::getName() : $name;
    }

    public function profile($detail = true): array
    {
        $profile = parent::profile($detail);

        $profile['avatar'] = $profile['headimgurl'];
        $profile['mobile'] = $this->getMobile();
        $profile['level'] = $this->getAgentLevel();
        $profile['company'] = $this->agentData('company', '');

        return $profile;
    }

    /**
     * @return referralModelObj
     */
    public function getReferral(): ?referralModelObj
    {
        /** @var referralModelObj $referral */
        $referral = m('referral')->findOne(['agent_id' => $this->getId()]);
        if (empty($referral)) {
            do {
                $code = Util::random(6, true);
            } while (m('referral')->findOne(['code' => $code]));

            $referral = m('referral')->create(['agent_id' => $this->getId(), 'code' => $code]);
        }

        return $referral;
    }

    /**
     * 设备数量
     * @return int
     */
    public function getDeviceCount(): int
    {
        $count = Device::query(['agent_id' => $this->getAgentId()])->count();

        return intval($count);
    }

    /**
     * @return int
     */
    public function getAgentId(): int
    {
        if ($this->isAgent()) {
            return $this->getId();
        }

        return $this->getPartnerAgentId();
    }

    /**
     * 返回用户代理商身份
     * @return agentModelObj
     */
    public function getAgent(): ?agentModelObj
    {
        return $this;
    }

    /**
     * 获取合伙人的代理商ＩＤ
     * @return int
     */
    public function getPartnerAgentId(): int
    {
        if ($this->isPartner()) {
            $partner_data = $this->get('partnerData', []);

            return intval($partner_data['agent']);
        }

        return 0;
    }

    /**
     * 指定代理商等级比当前用户代理商等级，大返回1，一样返回0,小返回-1
     * @param agentModelObj|string $agent
     * @return bool|int
     */
    public function agentLevelCompare($agent)
    {
        if ($agent && $this->isAgent()) {

            if (is_string($agent)) {
                $agent_level = $agent;
            } else {
                $agent_level = $agent->settings('agentData.level', '');
            }

            $self_level = $this->settings('agentData.level', '');

            return strcmp($agent_level, $self_level);
        }

        return false;
    }

    /**
     * @param string $path
     * @return mixed
     */
    public function getAgentData($path = '')
    {
        if ($this->isAgent()) {
            $key = 'agentData';
            if (!empty($path)) {
                $key .= ".{$path}";
            }
            return $this->settings($key, []);
        }

        if ($this->isPartner()) {
            $agent = $this->getPartnerAgent();
            if ($agent) {
                return $agent->getAgentData($path);
            }
        }

        return null;
    }

    public function isPaymentConfigEnabled(): bool
    {
        return $this->getAgentData('pay.wx.enable') || $this->getAgentData('pay.lcsw.enable');
    }

    /**
     * 删除合伙人
     * @param userModelObj|int $user
     * @return bool
     */
    public function removePartner($user): bool
    {
        if ($this->isAgent() && $user) {

            $agent_data = $this->settings('agentData', []);

            $classname = m('user')->objClassname();
            if ($user instanceof $classname) {

                $user_id = $user->getId();
                unset($agent_data['partners'][$user_id]);

                $partner = $user;

            } else {

                $user_id = intval($user);
                unset($agent_data['partners'][$user_id]);

                $partner = User::get($user_id);
            }

            if ($this->updateSettings('agentData', $agent_data)) {
                if ($partner && $partner->isPartner()) {
                    return $partner->removePrincipal(User::PARTNER) && $partner->remove('partnerData');
                }

                return true;
            }
        }

        return false;
    }

    /**
     * 把指定用户设置成合伙人
     * @param userModelObj $user
     * @param string $name
     * @param string $mobile
     * @param array $notice
     * @return bool
     */
    public function setPartner(userModelObj $user, string $name, string $mobile, array $notice = []): bool
    {
        if ($this->isAgent()) {
            $classname = m('user')->objClassname();
            if ($user instanceof $classname && !$user->isAgent() && $mobile) {

                if (!$user->isPartner()) {
                    if (!$user->setPrincipal(User::PARTNER)) {
                        return false;
                    }
                }

                if ($user->updateSettings(
                    'partnerData',
                    [
                        'name' => $name,
                        'mobile' => $mobile,
                        'agent' => $this->getId(),
                        'createtime' => TIMESTAMP,
                    ]
                )) {

                    $user->setMobile($mobile);
                    $user->save();
                    if ($this->updateSettings(
                        "agentData.partners.{$user->getId()}",
                        [
                            'openid' => $user->getOpenid(),
                            'name' => $name,
                            'mobile' => $mobile,
                            'notice' => $notice,
                            'createtime' => TIMESTAMP,
                        ]
                    )) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * 获取代理商的等级信息
     */
    public function getAgentLevel(): array
    {
        $levels = settings('agent.levels');
        $res = array_intersect(array_keys($levels), $this->getPrincipals());
        if ($res) {
            $res = array_values($res);
            $data = $levels[$res[0]];
            $data['level'] = $res[0];

            return $data;
        }

        return [];
    }

    /**
     * @param string $month
     * @return array
     * @throws
     */
    public function getMTotal(string $month = ''): array
    {
        $result = [
            'total' => 0,
            'free' => 0,
            'fee' => 0,
            'balance' => 0,
        ];

        if (!($this->isAgent() || $this->isPartner())) {
            return $result;
        }

        if (empty($month)) {
            $month = 'first day of this month';
        }

        $M_total = $this->get('M_total', []);

        $date = date_create(date('Y-m-01 00:00:00', strtotime($month)));

        $begin = $date->getTimestamp();
        $m_label = $date->format('Ym');

        $date->modify('first day of next month');
        $end = $date->getTimestamp();

        if ($M_total && $M_total[$m_label]) {
            return $M_total[$m_label];
        }

        $result['begin'] = date('Y-m-d', $begin);
        $result['end'] = date('Y-m-d', $end - 1);

        $agent_id = $this->getAgentId();
        if ($agent_id) {
            $result['free'] = (int)m('order')
                ->where(We7::uniacid(['agent_id' => $agent_id, 'price' => 0, 'balance' => 0]))
                ->where(['createtime >=' => $begin, 'createtime <' => $end])
                ->get('sum(num)');

            $result['fee'] = (int)m('order')
                ->where(We7::uniacid(['agent_id' => $agent_id, 'price >' => 0]))
                ->where(['createtime >=' => $begin, 'createtime <' => $end])
                ->get('sum(num)');

            $result['balance'] = (int)m('order')
                ->where(We7::uniacid(['agent_id' => $agent_id, 'balance >' => 0]))
                ->where(['createtime >=' => $begin, 'createtime <' => $end])
                ->get('sum(num)');

            $result['total'] = $result['fee'] + $result['free'] + $result['balance'];

            if ($m_label != date('Ym')) {
                $M_total[$m_label] = $result;
                $this->set('M_total', $M_total);
            }
        }

        return $result;
    }

    /**
     * 是否为指定代理商的合伙人
     * @param agentModelObj|string $agent
     * @return bool
     */
    public function isPartnerOf($agent): bool
    {
        $user_classname = m('user')->objClassname();
        /** @var agentModelObj $agent */
        if ($agent instanceof $user_classname) {
            return $this->getPartnerAgentId() == $agent->getId();
        }
        return $this->getPartnerAgentId() == intval($agent);
    }

    public function getGSPMode(): string
    {
        static $mode = null;
        if (!isset($mode)) {
            $config = $this->settings('agentData.gsp', []);
            $mode = $config['enabled'] ? strval($config['mode']) : '';
        }
        return $mode;
    }

    /**
     * 获取佣金分享用户列表
     * @param deviceModelObj|null $device
     * @return array
     */
    public function getGspUsers(deviceModelObj $device = null): array
    {
        $result = [];

        $order_settings = $this->settings('agentData.gsp.order', [
            'f' => 1,
            'b' => 1,
            'p' => 1,
        ]);

        $gsp_mode = $this->getGSPMode();

        if ($gsp_mode == GSP::REL) {
            //三级分佣模式
            if (empty($device) || $device->getAgentId() == $this->getId()) {
                //1，直接上级
                $superior = $this->getSuperior();
                if ($superior && $superior->settings('agentData.commission.enabled')) {

                    $result[] = [
                        'percent' => $this->settings('agentData.gsp.rel.level1', 0),
                        '__obj' => $superior,
                        'type' => $this->settings('agentData.gsp.mode_type', 'percent'),
                        'order' => $order_settings,
                    ];

                    //上级的上级
                    $x_superior = $superior->getSuperior();
                    if ($x_superior && $x_superior->settings('agentData.commission.enabled')) {
                        $result[] = [
                            'percent' => $this->settings('agentData.gsp.rel.level2', 0),
                            '__obj' => $x_superior,
                            'type' => $this->settings('agentData.gsp.mode_type', 'percent'),
                            'order' => $order_settings,
                        ];

                        //上上上级
                        $xx_superior = $x_superior->getSuperior();
                        if ($xx_superior && $xx_superior->settings('agentData.commission.enabled')) {
                            $result[] = [
                                'percent' => $this->settings('agentData.gsp.rel.level3', 0),
                                '__obj' => $xx_superior,
                                'type' => $this->settings('agentData.gsp.mode_type', 'percent'),
                                'order' => $order_settings,
                            ];
                        }
                    }
                }
            }

        } elseif ($gsp_mode == GSP::FREE) {
            //自由分佣模式
            $gsp_users = $this->settings('agentData.gsp.users', []);
            foreach ($gsp_users as $openid => $entry) {

                $user = User::get($openid, true);

                if ($user && $entry['percent'] > 0) {
                    if (empty($device) || empty($entry['assigned']) || $device->isMatched($entry['assigned'])) {
                        $data = [
                            '__obj' => $user,
                            'order' => is_array($entry['order']) ? $entry['order'] : $order_settings,
                        ];
                        if ($entry['percent'] > 0) {
                            $data['percent'] = floatval($entry['percent']);
                            $data['type'] = 'percent';
                        } else if ($entry['amount'] > 0) {
                            $data['percent'] = floatval($entry['amount']);
                            $data['type'] = 'amount';
                        }
                        $result[] = $data;
                    }
                }
            }
        }

        return $result;
    }

    public function tryLock(): bool
    {
        $locked = false;
        for ($i = 0; $i < 10; $i++) {
            $locked = !!$this->lock();
            if (!$locked) {
                usleep(100000);
            } else {
                break;
            }
        }
        return $locked;
    }
}
