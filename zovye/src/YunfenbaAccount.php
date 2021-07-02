<?php


namespace zovye;


use Exception;
use RuntimeException;
use zovye\model\userModelObj;
use zovye\model\deviceModelObj;

class YunfenbaAccount
{
    const GET_TASK_URL = 'http://api.goluodi.com/{vendor_uid}/gettask';
    //const GET_TASK_URL = 'http://testapi.goluodi.com/{vendor_uid}/gettask';

    private $vendor_uid;

    public function __construct($vendor_uid)
    {
        $this->vendor_uid = strval($vendor_uid);
    }

    public static function getUid(): string
    {
        return Account::makeSpecialAccountUID(Account::YUNFENBA, Account::YUNFENBA_NAME);
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
                'val' => 'scene_1'
            ],
            [
                'title' => '亲子',
                'val' => 'scene_2'
            ],
            [
                'title' => '休闲娱乐',
                'val' => 'scene_3'
            ],
            [
                'title' => '房产小区',
                'val' => 'scene_4'
            ],
            [
                'title' => '教育学校',
                'val' => 'scene_5'
            ],
            [
                'title' => '旅游景点',
                'val' => 'scene_6'
            ],
            [
                'title' => '机构团体',
                'val' => 'scene_7'
            ],
            [
                'title' => '汽车服务',
                'val' => 'scene_8'
            ],
            [
                'title' => '餐饮美食',
                'val' => 'scene_9'
            ],

            [
                'title' => '运动健身',
                'val' => 'scene_10'
            ],

            [
                'title' => '商务办公',
                'val' => 'scene_11'
            ],

            [
                'title' => '银行金融',
                'val' => 'scene_12'
            ],
            [
                'title' => '飞机场',
                'val' => 'scene_13'
            ],
            [
                'title' => '医疗保健',
                'val' => 'scene_14'
            ],
            [
                'title' => '高铁站',
                'val' => 'scene_15'
            ],
            [
                'title' => '工厂',
                'val' => 'scene_16'
            ],

            [
                'title' => '列车',
                'val' => 'scene_17'
            ],
            [
                'title' => '大巴',
                'val' => 'scene_18'
            ],
            [
                'title' => '商务酒店',
                'val' => 'scene_19'
            ],
        ];
    }

    public function getTask(deviceModelObj $device, userModelObj $user, callable $cb = null): array
    {
        $url = str_replace('{vendor_uid}', $this->vendor_uid, self::GET_TASK_URL);

        $scene = $device->settings('extra.yunfenba.scene', '');

        $fans = empty($user) ? Util::fansInfo() : $user->profile();

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

        $result = Util::post($url, $data);

        if ($cb) {
            $cb($data, $result);
        }

        if (is_error($result)) {
            return $result;
        }

        if (!empty($result['errcode'])) {
            if ($result['errcode'] == 203) {
                return err('暂时没有公众号！');
            }
            return err('接口返回错误！');
        }

        return $result['data'];
    }

    /**
     * 请求一个公众号
     * @param deviceModelObj $device
     * @param userModelObj $user
     * @return array
     */
    public static function fetch(deviceModelObj $device, userModelObj $user): array
    {
        $acc = Account::findOne(['state' => Account::YUNFENBA]);
        if ($acc) {
            $config = $acc->settings('config', []);
            if (empty($config) || empty($config['vendor']['uid'])) {
                return err('没有配置渠道商UID！');
            }

            //请求对方API
            $yunfenba = new YunfenbaAccount($config['vendor']['uid']);
            $result = $yunfenba->getTask($device, $user, function ($request, $result) use ($acc, $device, $user) {
                $log = Account::createQueryLog($acc, $user, $device, $request, $result);
                if (empty($log)) {
                    Util::logToFile('yunfenba_query', [
                        'query' => $request,
                        'result' => $result,
                    ]);
                }
            });
            if (is_error($result)) {
                Util::logToFile('yunfenba', [
                    'user' => $user->profile(),
                    'acc' => $acc->getName(),
                    'device' => $device->getName(),
                    'data' => request::raw(),
                    'error' => $result,
                ]);
            } else {
                $data = $acc->format();

                $data['title'] = $result['wechat_name'];
                $data['img'] = $result['headimg_url'];
                $data['qrcode'] = $result['qrcode_url'];

                return [$data];
            }
        }

        return [];
    }

    public static function verifyData($params): array
    {
        unset($params);

        if (!App::isYunfenbaEnabled()) {
            return err('没有启用！');
        }

        $acc = Account::findOne(['state' => Account::YUNFENBA]);
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
                throw new RuntimeException('发生错误：' . $res['message']);
            }

            /** @var userModelObj $user */
            $user = User::get($params['user'], true);
            if (empty($user) || $user->isBanned()) {
                throw new RuntimeException('找不到指定的用户或者已禁用');
            }

            /** @var deviceModelObj $device */
            $device = Device::findOne(['shadow_id' => $params['device']]);
            if (empty($device)) {
                throw new RuntimeException('找不到指定的设备:' . $params['state']);
            }

            $nostr = Util::random(16, true);
            $order_uid = substr("U{$user->getId()}D{$device->getId()}{$nostr}", 0, MAX_ORDER_NO_LEN);

            $acc = $res['account'];

            $log = Account::getLastQueryLog($acc, $user, $device);
            if ($log) {
                $log->setExtraData('cb', [
                    'time' => time(),
                    'order_uid' => $order_uid,
                    'data' => $params,
                ]);
                $log->save();
            }
            Job::createSpecialAccountOrder([
                'device' => $device->getId(),
                'user' => $user->getId(),
                'account' => $acc->getId(),
                'orderUID' => $order_uid,
            ]);
        } catch (Exception $e) {
            Util::logToFile('yunfenba', [
                'error' => '发生错误! ',
                'result' => $e->getMessage(),
            ]);
        }
    }
}
