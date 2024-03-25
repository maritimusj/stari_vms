<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use RuntimeException;
use zovye\business\Charging;
use zovye\business\Fueling;
use zovye\domain\Device;
use zovye\domain\DeviceEvents;
use zovye\domain\Order;
use zovye\domain\User;
use zovye\util\Helper;
use zovye\util\QRCodeUtil;
use zovye\util\Util;

class DeviceEventProcessor
{
    const EVENT_V0_INIT = 'init';
    const EVENT_V0_UPDATE = 'update';
    const EVENT_V0_INFO = 'info';
    const EVENT_V0_QRCODE = 'qrcode';
    const EVENT_V0_CONFIG = 'config';
    const EVENT_V0_OFFLINE = 'offline';

    const EVENT_V1_ONLINE = 'mcb.online';
    const EVENT_V1_OFFLINE = 'mcb.offline';
    const EVENT_V1_RESULT = 'mcb.result';
    const EVENT_V1_REPORT = 'mcb.report';
    const EVENT_V1_PING = 'mcb.ping';
    const EVENT_V1_FEE = 'mcb.fee';
    const EVENT_V1_CONFIG = 'mcb.config';

    protected static $events = [
        self::EVENT_V0_INIT => [
            'title' => '[v0]App初始化',
            'handler' => [self::class, 'onAppInitMsg'],
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 5,
                ],
            ],
        ],
        self::EVENT_V0_UPDATE => [
            'title' => '[v0]App更新配置',
            'handler' => [self::class, 'onAppConfigMsg'],
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 6,
                ],
            ],
        ],
        self::EVENT_V0_INFO => [
            'title' => '[v0]App上报信息',
            'handler' => [self::class, 'onAppInfoMsg'],
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 7,
                ],
            ],
        ],
        self::EVENT_V0_QRCODE => [
            'title' => '[v0]App请求更新二维码',
            'handler' => [self::class, 'onAppQRCodeMsg'],
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 9,
                ],
            ],
        ],
        self::EVENT_V0_CONFIG => [
            'title' => '[v0]App请求配置',
            'handler' => [self::class, 'onAppConfigMsg'],
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 10,
                ],
            ],
        ],
        self::EVENT_V0_OFFLINE => [
            'title' => '[v0]App离线',
            'handler' => [self::class, 'onAppOfflineMsg'],
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 22,
                ],
            ],
        ],
        self::EVENT_V1_ONLINE => [
            'title' => '[v1]上线，主板网络连接成功',
            'handler' => [self::class, 'onMcbOnline'],
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 11,
                ],
            ],
        ],
        self::EVENT_V1_OFFLINE => [
            'title' => '[v1]下线，主板离线',
            'handler' => [self::class, 'onMcbOffline'],
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 12,
                ],
            ],
        ],
        self::EVENT_V1_RESULT => [
            'title' => '[v1]出货结果',
            'handler' => [self::class, 'onMcbResult'],
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 13,
                ],
            ],
        ],
        self::EVENT_V1_REPORT => [
            'title' => '[v1]主板上报设备状态',
            'handler' => [self::class, 'onMcbReport'],
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 14,
                ],
            ],
        ],
        self::EVENT_V1_PING => [
            'title' => '[v1]心跳',
            'handler' => [self::class, 'onMcbPing'],
            'params' => [
                'log' => [
                    'enable' => false,
                    'id' => 18,
                ],
            ],
        ],
        self::EVENT_V1_FEE => [
            'title' => '[v1]计费信息',
            'handler' => [self::class, 'onMcbFee'],
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 23,
                ],
            ],
        ],
        self::EVENT_V1_CONFIG => [
            'title' => '[v1]配置更新',
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 24,
                ],
            ],
        ],
    ];

    public static function logEventTitle($id): string
    {
        static $cache = [];

        if (empty($cache[$id])) {
            foreach (self::$events as $entry) {
                if ($entry['params']['log']['id'] == $id) {
                    $cache[$id] = $entry;
                }
            }
        }

        return isset($cache[$id]) ? $cache[$id]['title'] : 'n/a';
    }

    /**
     * 处理实体硬件的事件
     * return mixed
     */
    public static function handle(string $event, array $data)
    {
        $e = self::$events[$event];
        if (!isset($e)) {
            throw new RuntimeException('找不到这个消息处理程序！');
        }

        // 消息日志
        self::log($e, $data);

        $fn = $e['handler'];

        if (!empty($fn)) {
            if (!is_callable($fn)) {
                throw new RuntimeException('指定的处理程序不正确！');
            }
            call_user_func($fn, $data);
        }
    }

    /**
     * 记录设备日志
     */
    public static function log(array $event, array $data = [])
    {
        $log = $event['params']['log'];
        if (isset($log) && $log['enable']) {
            if (isset($data['IMEI'])) {
                $device = Device::get($data['IMEI'], true);
            } elseif (isset($data['uid'])) {
                $device = Device::get($data['uid'], true);
            } elseif (isset($data['id'])) {
                $app_id = $data['id'];
                $device = Device::getFromAppId($app_id);
            }
            if (isset($device)) {
                if ($device->isEventLogEnabled()) {
                    $data = [
                        'event' => $log['id'],
                        'device_uid' => $device->getUid(),
                        'extra' => $data,
                    ];

                    if (!DeviceEvents::create($data)) {
                        Log::error('events', [
                            'error' => 'create device log failed',
                            'data' => $data,
                        ]);
                    }
                }
            } else {
                Log::warning('events', [
                    'msg' => 'device not exists',
                    'event' => $event,
                    'data' => $data,
                ]);
            }
        }
    }

    /**
     * offline 事件处理
     */
    public static function onAppOfflineMsg(array $data)
    {
        $app_id = $data['id'];
        $device = Device::getFromAppId($app_id);
        if ($device) {
            Device::processAppOnlineBonus($device);
        }
    }

    /**
     * app::qrcode 事件处理
     */
    public static function onAppQRCodeMsg($data)
    {
        $app_id = $data['id'];
        $device = Device::getFromAppId($app_id);
        if ($device) {
            $device->updateQRCode();
        }
    }

    /**
     * app::info 事件处理
     */
    public static function onAppInfoMsg(array $data)
    {
        $app_id = $data['id'];
        $device = Device::getFromAppId($app_id);
        if ($device) {
            if ($data['data']) {
                $data = $data['data'];
                //记录设备定位信息
                if ($data['lng'] && $data['lat']) {
                    $device->set(
                        'location',
                        [
                            'lng' => "{$data['lng']}",
                            'lat' => "{$data['lat']}",
                        ]
                    );
                }
            }

            $device->save();
        }
    }

    /**
     * app::init 事件处理
     */
    public static function onAppConfigMsg(array $data, bool $fetch = false)
    {
        $result = [];

        $app_id = $data['id'];
        $device = Device::getFromAppId($app_id);

        if ($device) {
            $result = $device->getAppConfig();
            $result['tags'] = $device->getTopics(); //要订阅的topics

            if (!empty($data['version'])) {
                $device->setAppVersion($data['version']);
                $device->save();
            }

            $result['mcbUID'] = strval($device->getImei());
        }

        if ($fetch) {
            return $result;
        }

        CtrlServ::appPublish($app_id, 'config', $result);

        return false;
    }

    /**
     * app::init 事件处理
     */
    public static function onAppInitMsg(array $data)
    {
        $app_id = $data['id'];
        $device = Device::getFromAppId($app_id);
        if ($device) {
            $config = $device->getAppConfig();
            $config['tags'] = $device->getTopics(); //要订阅的topics

            //是不是刷新操作？
            if ($device->has('refresh')) {
                $device->remove('refresh');
            } else {
                $device->setAppLastOnline(TIMESTAMP);
            }

            $device->setAppVersion($data['version']);
            $device->save();

            //检查设备ＡＰＰ是否需要更新
            //...

            //发送设备注册的MCB的IMEI
            //APP要根据这个判断是否创建虚拟ＭＣＢ
            $config['mcbUID'] = strval($device->getImei());
            $device->appPublishConfig($config);
        } else {
            $config = [
                'volume' => 10, //音量百分比 0 - 100
                'banner' => Util::toMedia(settings('misc.banner')), //固定引导图
                'advs' => [],
            ];

            //发送设备注册二维码
            $url = Util::murl('app', ['id' => $app_id]);
            $qrcode = QRCodeUtil::createFile("app.$app_id", $url);
            $config['qrcode'] = Util::toMedia($qrcode);
            $config['qrcode_url'] = $url;
            $config['reginfo'] = [
                'appId' => strval($app_id),
                'title' => strval(settings('misc.siteTitle')),
            ];

            CtrlServ::appPublish($app_id, 'config', $config);
        }
    }


    /**
     * v1版本 mcb::online 事件处理
     */
    public static function onMcbOnline(array $data = [])
    {
        $device = Device::get($data['uid'], true);
        if (empty($device)) {
            $device = Device::createNewDevice($data);
        }

        if ($device) {
            $device->setMcbOnline(Device::ONLINE);
            $device->setLastOnline(TIMESTAMP);
            $device->setLastPing(TIMESTAMP);
            $device->updateFirstMsgStats();

            if ($data['code']) {
                $device->setProtocolV1Code($data['code']);
            }

            if ($device->isMcbStatusExpired()) {
                $device->reportMcbStatus($data['code']);
            }

            $device->save();

            if ($device->isFuelingDevice()) {
                Fueling::onEventOnline($device);
            } else {
                $config = $device->settings('extra.mcb_config', []);
                if ($config) {
                    $device->mcbPublish('config', '', $config);
                }
            }

            Job::deviceEventNotify($device, Device::EVENT_ONLINE);
        }
    }


    /**
     * v1版本 mcb::offline 事件处理
     */
    public static function onMcbOffline(array $data = [])
    {
        $device = Device::get($data['uid'], true);
        if ($device) {
            $device->setMcbOnline(Device::OFFLINE);

            if ($device->isChargingDevice()) {
                $chargerNum = $device->getChargerNum();
                for ($i = 0; $i < $chargerNum; $i++) {
                    $device->setChargerProperty($i + 1, 'status', 0);
                }
            }

            $device->save();

            Job::deviceEventNotify($device, Device::EVENT_OFFLINE);
        }
    }


    /**
     * v1版本 mcb::result 事件处理
     */
    public static function onMcbResult(array $data = [])
    {
        ['uid' => $uid, 'code' => $code, 'extra' => $extra] = $data;

        if (!empty($uid) && is_array($extra)) {

            $device = Device::get($uid, true);
            if (empty($device)) {
                throw new RuntimeException('找不到这个设备！');
            }

            ['ser' => $serial, 're' => $re] = $extra;

            if ($device->isChargingDevice()) {
                Charging::onEventResult($device, $extra);
            } elseif ($device->isFuelingDevice()) {
                Fueling::onEventResult($device, $extra);
            } elseif ($device->isBlueToothDevice()) {
                if ($serial && $re === 3) {
                    $order = Order::get($serial, true);
                    if ($order) {
                        $order->setResultCode(0);
                        $order->setExtraData('pull.callback', $data);
                        $order->save();
                    }
                }
            } elseif ($device->isNormalDevice()) {
                if ($code) {
                    $device->setProtocolV1Code($code);
                }
                if (empty($serial)) {
                    //创建匿名订单
                    $user = User::getPseudoUser();
                    $goods = $device->getGoodsByLane(0);
                    $order_data = [
                        'src' => Order::FREE,
                        'order_id' => Order::makeUID($user, $device, TIMESTAMP),
                        'openid' => $user->getOpenid(),
                        'user_id' => $user->getId(),
                        'agent_id' => $device->getAgentId(),
                        'device_id' => $device->getId(),
                        'name' => $goods['name'],
                        'goods_id' => $goods['id'],
                        'num' => 1,
                        'price' => 0,
                        'ip' => CLIENT_IP,
                        'extra' => [
                            'level' => LOG_GOODS_FREE,
                            'goods' => $goods,
                            'device' => [
                                'imei' => $device->getImei(),
                                'name' => $device->getName(),
                            ],
                            'user' => $user->profile(),
                        ],
                    ];
                    Order::create($order_data);
                }
            }

            $device->save();
        }
    }

    /**
     * v1版本 mcb::report 事件处理
     */
    public static function onMcbReport(array $data = [])
    {
        $device = Device::get($data['uid'], true);
        if (empty($device)) {
            throw new RuntimeException('找不到这个设备！');
        }

        $device->setLastPing(time());
        $device->setMcbOnline(Device::ONLINE);
        $device->setLastOnline(TIMESTAMP);

        if ($data['code']) {
            $device->setProtocolV1Code($data['code']);
        }

        $extra = (array)$data['extra'];

        if (isset($extra['ICCID'])) {
            $device->setICCID($extra['ICCID']);
        }

        if (isset($extra['iccid'])) {
            $device->setICCID($extra['iccid']);
        }

        if (isset($extra['ip'])) {
            $device->setLastOnlineIp(strval($extra['ip']));
        }

        if (isset($extra['RSSI'])) {
            $device->setSig($extra['RSSI']);
        }

        if (isset($extra['LAC'])) {
            if (settings('device.lac.enabled')) {
                $lastLAC = $device->settings('extra.v1.lac.v', '');
                if (!empty($lastLAC) && $lastLAC != $extra['LAC'] && empty($device->getS1())) {
                    $device->setS1(1);
                    $device->UpdateSettings('extra.v1.lac.time', time());
                }
            }

            $device->updateSettings('extra.v1.lac.v', strval($extra['LAC']));
        }

        if (isset($extra['qoe'])) {
            $device->setQoe($extra['qoe']);
        }

        if (isset($extra['voltage'])) {
            $device->setV0Status(Device::V0_STATUS_VOLTAGE, $extra['voltage']);
        }

        if (isset($extra['count'])) {
            $device->setV0Status(Device::V0_STATUS_COUNT, $extra['count']);
        }

        if (isset($extra['error'])) {
            $device->setV0Status(Device::V0_STATUS_ERROR, $extra['error']);
        }

        if ($device->isNormalDevice()) {
            $device->updateMcbStatus($extra);
            //用户扫码购买
            if (isset($extra['payment'])) {
                Helper::createQrcodeOrder($device, (array)$extra['payment']);
            }
        } elseif ($device->isChargingDevice()) {
            Charging::onEventReport($device, $extra);
        } elseif ($device->isFuelingDevice()) {
            Fueling::onEventReport($device, $extra);
        }

        $device->save();
    }

    /**
     * v1版本 mcb::ping 事件处理
     */
    public static function onMcbPing(array $data = [])
    {
        $device = Device::get($data['uid'], true);
        if ($device) {
            $device->setMcbOnline(Device::ONLINE);
            $device->setLastPing(TIMESTAMP);
            $device->setLastOnline(TIMESTAMP);

            if ($device->isChargingDevice()) {
                $chargerID = $data['chargerID'];
                $property = [
                    'lastActive' => time(),
                ];
                if ($data['status'] == 1) {
                    $property['status'] = 1;
                }
                $device->setChargerProperty($chargerID, $property);
            }

            $device->save();
        }
    }

    public static function onMcbFee(array $data = [])
    {
        $device = Device::get($data['uid'], true);
        if ($device && $device->isFuelingDevice()) {
            Fueling::onEventFee($device, (array)$data['extra']);
        }
    }
}
