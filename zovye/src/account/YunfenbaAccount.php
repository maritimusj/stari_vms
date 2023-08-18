<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\account;

use Exception;
use RuntimeException;
use zovye\Account;
use zovye\App;
use zovye\Contract\IAccountProvider;
use zovye\Device;
use zovye\HttpUtil;
use zovye\Log;
use zovye\model\accountModelObj;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\Order;
use zovye\Session;
use zovye\User;
use function zovye\err;
use function zovye\is_error;

class YunfenbaAccount implements IAccountProvider
{
    const GET_TASK_URL = 'http://api.goluodi.com/{vendor_uid}/gettask';
    //const GET_TASK_URL = 'http://testapi.goluodi.com/{vendor_uid}/gettask';

    private $vendor_uid;
    private $sub_uid;

    public function __construct($vendor_uid, $sub_uid = '')
    {
        $this->vendor_uid = strval($vendor_uid);
        $this->sub_uid = $sub_uid;
    }

    public static function getUID(): string
    {
        return Account::makeThirdPartyPlatformUID(Account::YUNFENBA, Account::YUNFENBA_NAME);
    }

    /**
     * 返回场景列表
     * @return array
     */
    public static function fetchSceneList(): array
    {
        return [
            [
                'title' => '购物商圈',
                'val' => 'scene_1',
            ],
            [
                'title' => '亲子',
                'val' => 'scene_2',
            ],
            [
                'title' => '休闲娱乐',
                'val' => 'scene_3',
            ],
            [
                'title' => '房产小区',
                'val' => 'scene_4',
            ],
            [
                'title' => '教育学校',
                'val' => 'scene_5',
            ],
            [
                'title' => '旅游景点',
                'val' => 'scene_6',
            ],
            [
                'title' => '机构团体',
                'val' => 'scene_7',
            ],
            [
                'title' => '汽车服务',
                'val' => 'scene_8',
            ],
            [
                'title' => '餐饮美食',
                'val' => 'scene_9',
            ],

            [
                'title' => '运动健身',
                'val' => 'scene_10',
            ],

            [
                'title' => '商务办公',
                'val' => 'scene_11',
            ],

            [
                'title' => '银行金融',
                'val' => 'scene_12',
            ],
            [
                'title' => '飞机场',
                'val' => 'scene_13',
            ],
            [
                'title' => '医疗保健',
                'val' => 'scene_14',
            ],
            [
                'title' => '高铁站',
                'val' => 'scene_15',
            ],
            [
                'title' => '工厂',
                'val' => 'scene_16',
            ],

            [
                'title' => '列车',
                'val' => 'scene_17',
            ],
            [
                'title' => '大巴',
                'val' => 'scene_18',
            ],
            [
                'title' => '商务酒店',
                'val' => 'scene_19',
            ],
        ];
    }

    public function getTask(deviceModelObj $device, userModelObj $user = null, callable $cb = null): array
    {
        $url = str_replace('{vendor_uid}', $this->vendor_uid, self::GET_TASK_URL);
        if (!empty($this->sub_uid)) {
            $url .= "/$this->sub_uid";
        }

        $scene = $device->settings('extra.yunfenba.scene', '');

        $fans = empty($user) ? Session::fansInfo() : $user->profile();

        $data = [
            'userid' => $fans['openid'],
            'nickname' => $fans['nickname'],
            'user_area' => "{$fans['province']} {$fans['city']}",
            'sex' => empty($fans['sex']) ? 0 : $fans['sex'],
            'ua' => $_SERVER['HTTP_USER_AGENT'],
            'device' => $device->getShadowId(),
            'user' => $user->getOpenid(),
        ];

        if (!empty($scene)) {
            $data['scene'] = $scene;
        }

        $area = $device->getArea();
        if ($area) {
            $data['device_area'] = implode('', $area);
        }

        $result = HttpUtil::post($url, $data);

        if ($cb) {
            $cb($data, $result);
        }

        return $result;
    }

    /**
     * 请求一个公众号
     * @param deviceModelObj $device
     * @param userModelObj $user
     * @return array
     */
    public static function fetch(deviceModelObj $device, userModelObj $user): array
    {
        $v = [];

        $acc = Account::findOneFromType(Account::YUNFENBA);
        if ($acc) {
            $config = $acc->settings('config', []);
            if (empty($config) || empty($config['vendor']['uid'])) {
                return err('没有配置渠道商UID！');
            }

            //请求对方API
            $yunfenba = new self($config['vendor']['uid'], $config['vendor']['sid']);

            $yunfenba->getTask($device, $user, function ($request, $result) use ($acc, $device, $user, &$v) {
                if (App::isAccountLogEnabled()) {
                    $log = Account::createQueryLog($acc, $user, $device, $request, $result);
                    if (empty($log)) {
                        Log::error('yunfenba_query', [
                            'query' => $request,
                            'result' => $result,
                        ]);
                    }
                }

                try {
                    if (empty($result)) {
                        throw new RuntimeException('返回数据为空！');
                    }

                    if (is_error($result)) {
                        throw new RuntimeException($result['message']);
                    }

                    if (!empty($result['errcode'])) {
                        if ($result['errcode'] == 203) {
                            throw new RuntimeException('暂时没有公众号！');
                        }
                        throw new RuntimeException('失败，错误代码：'.$result['errcode']);
                    }

                    if (empty($result['data']['qrcode_url'])) {
                        throw new RuntimeException('二维码网址为空！');
                    }

                    $data = $acc->format();

                    $data['title'] = $result['data']['wechat_name'] ?: Account::YUNFENBA_NAME;
                    $data['img'] = $result['data']['headimg_url'] ?: Account::YUNFENBA_HEAD_IMG;
                    $data['qrcode'] = $result['data']['qrcode_url'];

                    $v[] = $data;

                } catch (Exception $e) {
                    if (App::isAccountLogEnabled() && isset($log)) {
                        $log->setExtraData('error_msg', $e->getMessage());
                        $log->save();
                    } else {
                        Log::error('yunfenba', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
        }

        return $v;
    }

    public static function verifyData($params): array
    {
        unset($params);

        if (!App::isYunfenbaEnabled()) {
            return err('没有启用！');
        }

        $acc = Account::findOneFromType(Account::YUNFENBA);
        if (empty($acc)) {
            return err('找不到指定公众号！');
        }

        return ['account' => $acc];
    }

    public static function cb($params = [])
    {
        //出货流程
        try {
            $res = self::verifyData($params);
            if (is_error($res)) {
                throw new RuntimeException('发生错误：'.$res['message']);
            }

            /** @var userModelObj $user */
            $user = User::get($params['user'], true);
            if (empty($user) || $user->isBanned()) {
                throw new RuntimeException('找不到指定的用户或者已禁用');
            }

            /** @var accountModelObj $acc */
            $acc = $res['account'];

            if ($acc->getBonusType() == Account::BALANCE) {
                //todo 暂时没有文档说明返回数据中的唯一标识值
                $uid = $params['order_id'] ?? time();
                $serial = sha1("{$user->getId()}{$acc->getUid()}$uid");
                $result = Account::createThirdPartyPlatformBalance($acc, $user, $serial, $params);
                if (is_error($result)) {
                    throw new RuntimeException($result['message'] ?: '奖励积分处理失败！');
                }
            } else {
                /** @var deviceModelObj $device */
                $device = Device::findOne(['shadow_id' => $params['device']]);
                if (empty($device)) {
                    throw new RuntimeException('找不到指定的设备:'.$params['device']);
                }

                $order_uid = Order::makeUID($user, $device, sha1($params['wxid'] ?? REQUEST_ID));
                Account::createThirdPartyPlatformOrder($acc, $user, $device, $order_uid, $params);
            }

        } catch (Exception $e) {
            Log::error('yunfenba', [
                'error' => '发生错误! ',
                'result' => $e->getMessage(),
            ]);
        }
    }
}
