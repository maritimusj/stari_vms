<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\GSP;
use zovye\Principal;
use zovye\User;
use zovye\Util;
use zovye\Device;

use function zovye\err;
use function zovye\is_error;
use function zovye\m;

/**
 * Class agentModelObj
 * @package zovye
 */
class agentModelObj extends userModelObj
{
    public function agentData($path, $default = null)
    {
        return $this->settings("agentData.$path", $default);
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
     * 设备数量
     * @return int
     */
    public function getDeviceCount(): int
    {
        return Device::query(['agent_id' => $this->getAgentId()])->count();
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

    public function isCommissionEnabled(): bool
    {
        return $this->settings('agentData.commission.enabled') ?? false;
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


    public function allowReduceGoodsNum(): bool
    {
        return (bool)$this->getAgentData('keeper.reduceGoodsNum.enabled', true);
    }

    public function isPaymentConfigEnabled(): bool
    {
        $pay = $this->getAgentData('pay', []);
        foreach ((array)$pay as $config) {
            if (is_array($config) && $config['enable']) {
                return true;
            }
        }

        return false;
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
                    return $partner->removePrincipal(Principal::Partner) && $partner->remove('partnerData');
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
        if (!$this->isAgent()) {
            return false;
        }

        if ($user->isAgent()) {
            return false;
        }

        if (empty($mobile)) {
            return false;
        }

        $result = Util::transactionDo(function () use ($user, $name, $mobile, $notice) {
            if (!$user->setPrincipal(Principal::Partner)) {
                return err('设置身份失败！');
            }

            if (!$user->updateSettings('partnerData', [
                'name' => $name,
                'mobile' => $mobile,
                'agent' => $this->getId(),
                'createtime' => TIMESTAMP,
            ])) {
                return err('保存数据失败！');
            }

            $user->setMobile($mobile);

            if (!$user->save()) {
                return err('保存数据失败！');
            }

            if (!$this->updateSettings("agentData.partners.{$user->getId()}", [
                'openid' => $user->getOpenid(),
                'name' => $name,
                'mobile' => $mobile,
                'notice' => $notice,
                'createtime' => TIMESTAMP,
            ])) {
                return err('保存数据失败！');
            }

            return true;
        });

        return !is_error($result);
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
                if ($superior && $superior->isCommissionEnabled()) {

                    $result[] = [
                        'val' => $this->settings('agentData.gsp.rel.level1', 0),
                        '__obj' => $superior,
                        'type' => $this->settings('agentData.gsp.mode_type', GSP::PERCENT),
                        'order' => $order_settings,
                    ];

                    //上级的上级
                    $x_superior = $superior->getSuperior();
                    if ($x_superior && $x_superior->isCommissionEnabled()) {
                        $result[] = [
                            'val' => $this->settings('agentData.gsp.rel.level2', 0),
                            '__obj' => $x_superior,
                            'type' => $this->settings('agentData.gsp.mode_type', GSP::PERCENT),
                            'order' => $order_settings,
                        ];

                        //上上上级
                        $xx_superior = $x_superior->getSuperior();
                        if ($xx_superior && $xx_superior->isCommissionEnabled()) {
                            $result[] = [
                                'val' => $this->settings('agentData.gsp.rel.level3', 0),
                                '__obj' => $xx_superior,
                                'type' => $this->settings('agentData.gsp.mode_type', GSP::PERCENT),
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
                if ($user) {
                    if (empty($device) || empty($entry['assigned']) || $device->isMatched($entry['assigned'])) {
                        $data = [
                            '__obj' => $user,
                            'order' => is_array($entry['order']) ? $entry['order'] : $order_settings,
                        ];
                        if ($entry['percent'] > 0) {
                            $data['val'] = floatval($entry['percent']);
                            $data['type'] = 'percent';
                        } else {
                            if ($entry['amount'] > 0) {
                                $data['val'] = floatval($entry['amount']);
                                $data['type'] = 'amount';
                            }
                        }
                        $result[] = $data;
                    }
                }
            }
        }

        return $result;
    }

    public function getFirstOrderData()
    {
        return $this->settings('agentData.stats.first_order');
    }

    public function setFirstOrderData(orderModelObj $order): bool
    {
        return $this->updateSettings('agentData.stats.first_order', [
            'id' => $order->getId(),
            'createtime' => $order->getCreatetime(),
        ]);
    }
}
