<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

class DeviceEventProcessor
{
    const EVENT_V0_PING = 'ping';
    const EVENT_V0_FIRST = 'first';
    const EVENT_V0_RESET = 'reset';
    const EVENT_V0_RESULT = 'result';
    const EVENT_V0_INIT = 'init';
    const EVENT_V0_UPDATE = 'update';
    const EVENT_V0_INFO = 'info';
    const EVENT_V0_ADV = 'adv';
    const EVENT_V0_QRCODE = 'qrcode';
    const EVENT_V0_CONFIG = 'config';
    const EVENT_V0_OFFLINE = 'offline';

    const EVENT_V1_ONLINE = 'mcb.online';
    const EVENT_V1_OFFLINE = 'mcb.offline';
    const EVENT_V1_RESULT = 'mcb.result';
    const EVENT_V1_RECORD = 'mcb.record';
    const EVENT_V1_REPORT = 'mcb.report';
    const EVENT_V1_M_REPORT = 'mcb.m-report';
    const EVENT_V1_RELOAD = 'mcb.reload';
    const EVENT_V1_RESET = 'mcb.reset';
    const EVENT_V1_PING = 'mcb.ping';
    const EVENT_V1_NEW_CARD = 'mcb.m-newcard';

    const EVENT_V2_STARTUP = 'mcb.startup';

    protected static $events = [
        self::EVENT_V0_PING => [
            'title' => '[v0]心跳',
            'handler' => __NAMESPACE__.'\DeviceEventProcessor::onPingMsg',
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 1,
                ],
            ],
        ],
        self::EVENT_V0_FIRST => [
            'title' => '[v0]上线，主板网络连接成功',
            'handler' => __NAMESPACE__.'\DeviceEventProcessor::onFirstMsg',
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 2,
                ],
            ],
        ],
        self::EVENT_V0_RESET => [
            'title' => '[v0]补货信号',
            'handler' => __NAMESPACE__.'\DeviceEventProcessor::onResetMsg',
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 3,
                ],
            ],
        ],
        self::EVENT_V0_RESULT => [
            'title' => '出货结果v0',
            'handler' => __NAMESPACE__.'\DeviceEventProcessor::onResultMsg',
            'params' => [
                'log' => [
                    'enabled' => true,
                    'id' => 4,
                ],
            ],
        ],
        self::EVENT_V0_INIT => [
            'title' => '[v0]App上线',
            'handler' => __NAMESPACE__.'\DeviceEventProcessor::onAppInitMsg',
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 5,
                ],
            ],
        ],
        self::EVENT_V0_UPDATE => [
            'title' => '[v0]App更新配置',
            'handler' => __NAMESPACE__.'\DeviceEventProcessor::onAppConfigMsg',
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 6,
                ],
            ],
        ],
        self::EVENT_V0_INFO => [
            'title' => '[v0]App上报信息',
            'handler' => __NAMESPACE__.'\DeviceEventProcessor::onAppInfoMsg',
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 7,
                ],
            ],
        ],
        self::EVENT_V0_ADV => [
            'title' => '[v0]App报告广告状态',
            'handler' => __NAMESPACE__.'\DeviceEventProcessor::onAppAdvMsg',
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 8,
                ],
            ],
        ],
        self::EVENT_V0_QRCODE => [
            'title' => '[v0]App请求更新二维码',
            'handler' => __NAMESPACE__.'\DeviceEventProcessor::onAppQrcodeMsg',
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 9,
                ],
            ],
        ],
        self::EVENT_V0_CONFIG => [
            'title' => '[v0]App请求配置',
            'handler' => __NAMESPACE__.'\DeviceEventProcessor::onAppConfigMsg',
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 10,
                ],
            ],
        ],
        self::EVENT_V1_ONLINE => [
            'title' => '[v1]上线，主板网络连接成功',
            'handler' => __NAMESPACE__.'\DeviceEventProcessor::onMcbOnline',
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 11,
                ],
            ],
        ],
        self::EVENT_V1_OFFLINE => [
            'title' => '[v1]下线，主板离线',
            'handler' => __NAMESPACE__.'\DeviceEventProcessor::onMcbOffline',
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 12,
                ],
            ],
        ],
        self::EVENT_V1_RESULT => [
            'title' => '[v1]出货结果',
            'handler' => __NAMESPACE__.'\DeviceEventProcessor::onMcbResult',
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 13,
                ],
            ],
        ],
        self::EVENT_V1_REPORT => [
            'title' => '[v1]主板上报设备状态',
            'handler' => __NAMESPACE__.'\DeviceEventProcessor::onMcbReport',
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 14,
                ],
            ],
        ],
        self::EVENT_V1_RELOAD => [
            'title' => '[v1]主板补货信号',
            'handler' => __NAMESPACE__.'\DeviceEventProcessor::onMcbReload',
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 15,
                ],
            ],
        ],
        self::EVENT_V1_RECORD => [
            'title' => '[v1]出货结果',
            'handler' => __NAMESPACE__.'\DeviceEventProcessor::onMcbRecord',
            'params' => [
                'log' => [
                    'enabled' => true,
                    'id' => 16,
                ],
            ],
        ],
        self::EVENT_V1_RESET => [
            'title' => '[v1]设备重启',
            'handler' => __NAMESPACE__.'\DeviceEventProcessor::onMcbReset',
            'params' => [
                'log' => [
                    'enabled' => true,
                    'id' => 17,
                ],
            ],
        ],
        self::EVENT_V1_PING => [
            'title' => '[v1]心跳',
            'handler' => __NAMESPACE__.'\DeviceEventProcessor::onMcbPing',
            'params' => [
                'log' => [
                    'enabled' => false,
                    'id' => 18,
                ],
            ],
        ],
        self::EVENT_V2_STARTUP => [
            'title' => '[v2]主板启动',
            'handler' => __NAMESPACE__.'\DeviceEventProcessor::onMcbStartup',
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 19,
                ],
            ],
        ],
        self::EVENT_V1_M_REPORT => [
            'title' => '[v1]主板上报设备状态(m-report)',
            'handler' => __NAMESPACE__.'\DeviceEventProcessor::onMcbMReport',
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 20,
                ],
            ],
        ],
        self::EVENT_V1_NEW_CARD => [
            'title' => '[v1]客户刷卡',
            'handler' => __NAMESPACE__.'\DeviceEventProcessor::onMcbNewCard',
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 21,
                ],
            ],
        ],
        self::EVENT_V0_OFFLINE => [
            'title' => '[v0]App离线',
            'params' => [
                'log' => [
                    'enable' => true,
                    'id' => 22,
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
     * 处理实体硬件的事件.
     *
     * @param string $event
     * @param array $data
     */
    public static function handle(string $event, array $data)
    {
        Util::transactionDo(function () use ($event, $data) {
            $e = self::$events[$event];
            if (isset($e)) {
                if (settings('device.event.enabled')) {
                    self::log($e, $data);
                }
                $fn = $e['handler'];
                if (!empty($fn)) {
                    if (is_callable($fn)) {
                        call_user_func($fn, $data);
                    } else {
                        Log::warning('events', [
                            'event' => $event,
                            'data' => $data,
                            'error' => 'handler is not function!',
                        ]);
                    }
                }
            } else {
                Log::warning('events', [
                    'event' => $event,
                    'data' => $data,
                    'error' => 'unhandled event!',
                ]);
            }
        });

        exit(CtrlServ::HANDLE_OK);
    }

    /**
     * 记录设备日志.
     *
     * @param array $event
     * @param array $data
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
                $data = We7::uniacid([
                    'event' => $log['id'],
                    'device_uid' => $device->getUid(),
                    'extra' => json_encode($data),
                ]);

                if (!m('device_events')->create($data)) {
                    Log::error('events', [
                        'error' => 'create device log failed',
                        'data' => $data,
                    ]);
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
     * ping 事件处理
     * @param array $data
     */
    public static function onPingMsg(array $data)
    {
        if ($data['IMEI']) {
            $device = Device::get($data['IMEI'], true);
            if ($device) {
                if (isset($data['Signal'])) {
                    $signal = intval($data['Signal']);
                    $device->setSig($signal);
                }
                $device->setLastPing(TIMESTAMP);
                $device->setLastOnline(TIMESTAMP);
                $device->save();
            }
        }
    }

    /**
     * first 事件处理
     * @param array $data
     */
    public static function onFirstMsg(array $data)
    {
        if ($data['IMEI']) {
            $device = Device::get($data['IMEI'], true);
            if (empty($device)) {
                $device = Device::createNewDevice($data);
            }

            if ($device) {
                if ($data['ICCID'] && $device->getICCID() != $data['ICCID']) {
                    $device->setICCID($data['ICCID']);
                }

                $device->setMcbOnline(Device::ONLINE);
                $device->setLastOnline(TIMESTAMP);

                $device->resetLock();
                $device->firstMsgStatistic();

                if ($device->isLastOnlineNotifyTimeout()) {
                    Job::deviceOnlineNotify($device);
                }
            }
        }
    }


    /**
     * reset 事件处理
     * @param array array $data
     */
    public static function onResetMsg(array $data)
    {
        if ($data['IMEI']) {
            $device = Device::get($data['IMEI'], true);
            if ($device) {
                if ($device->payloadLockAcquire(3)) {
                    $device->resetPayload([], 'reset重置');
                    $device->updateAppRemain();
                }
            }
        }
    }

    /**
     * result 事件处理.
     *
     * @param array $data
     */
    public static function onResultMsg(array $data)
    {
    }

    /**
     * app::adv　事件处理
     * @param array $data
     */
    public static function onAppAdvMsg(array $data)
    {
    }

    /**
     * app::qrcode 事件处理
     * @param $data
     */
    public static function onAppQrcodeMsg($data)
    {
        $app_id = $data['id'];
        $device = Device::getFromAppId($app_id);
        if ($device) {
            $device->updateQrcode();
        }
    }

    /**
     * app::info 事件处理
     * @param array $data
     */
    public static function onAppInfoMsg(array $data)
    {
        $app_id = $data['id'];
        $device = Device::getFromAppId($app_id);
        if ($device) {

            $device->setAppLastOnline(TIMESTAMP);

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
     * @param array $data
     * @param bool $fetch
     * @return array|bool
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

        CtrlServ::appNotify($app_id, 'config', $result);

        return false;
    }

    /**
     * app::init 事件处理
     * @param array $data
     */
    public static function onAppInitMsg(array $data)
    {
        $app_id = $data['id'];
        $device = Device::getFromAppId($app_id);
        if ($device) {
            $result = $device->getAppConfig();
            $result['tags'] = $device->getTopics(); //要订阅的topics

            //是不是刷新操作？
            if ($device->has('refresh')) {
                $device->remove('refresh');
            } else {
                $device->setAppLastOnline(time());
            }

            $device->setAppVersion($data['version']);
            $device->save();

            //检查设备ＡＰＰ是否需要更新
            //...

            //发送设备注册的MCB的IMEI
            //APP要根据这个判断是否创建虚拟ＭＣＢ
            $result['mcbUID'] = strval($device->getImei());

            $device->appNotify('config', $result);
        } else {
            $result = [
                'volume' => 10, //音量百分比 0 - 100
                'banner' => Util::toMedia(settings('misc.banner')), //固定引导图
                'advs' => [],
            ];

            //发送设备注册二维码
            $url = Util::murl('app', ['id' => $app_id]);
            $qrcode = Util::createQrcodeFile("app.$app_id", $url);
            $result['qrcode'] = strval(Util::toMedia($qrcode));
            $result['qrcode_url'] = $url;
            $result['reginfo'] = [
                'appId' => strval($app_id),
                'title' => strval(settings('misc.siteTitle')),
            ];

            CtrlServ::appNotify($app_id, 'config', $result);
        }
    }


    /**
     * v1版本 mcb::online 事件处理
     * @param array $data
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

            //重置设置锁
            $device->resetLock();
            $device->setProtocolV1Code($data['code']);

            if ($device->isMcbStatusExpired()) {
                $device->reportMcbStatus($data['code']);
            }

            $device->firstMsgStatistic();

            if ($device->isLastOnlineNotifyTimeout()) {
                Job::deviceOnlineNotify($device);
            }

            $device->save();
        }
    }


    /**
     * v1版本 mcb::offline 事件处理
     * @param array $data
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

            if ($device->isLastOnlineNotifyTimeout()) {
                Job::deviceOnlineNotify($device, '下线');
            }
        }
    }


    /**
     * v1版本 mcb::result 事件处理
     * @param array $data
     */
    public static function onMcbResult(array $data = [])
    {
        $device = Device::get($data['uid'], true);
        if ($device) {
            if ($device->isChargingDevice()) {
                $extra = (array)$data['extra'];
                $res = Charging::setResult($extra['ser'], $extra['re'], $extra);
                if (is_error($res)) {
                    Log::error('charging', [
                        'data' => $data,
                        'error' => $res,
                    ]);
                }
            }

            $device->setProtocolV1Code($data['code']);
            $device->save();
        }
    }


    /**
     * v1版本 m-report上报
     * @param array $data
     */
    public static function onMcbMReport(array $data = [])
    {
    }

    /**
     * v1版本  客户机刷卡事件
     * @param array $data
     */
    public static function onMcbNewCard(array $data = [])
    {
    }

    /**
     * v1版本 mcb::report 事件处理
     * @param array $data
     */
    public static function onMcbReport(array $data = [])
    {
        $device = Device::get($data['uid'], true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }
    
        $device->setLastPing(time());
        $device->setMcbOnline(Device::ONLINE);
        $device->setLastOnline(TIMESTAMP);

        $device->setProtocolV1Code($data['code']);

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

        if (isset($extra['sensor'])) {
            $sensors = isset($extra['sensor']['type']) ? [$extra['sensor']] : $extra['sensor'];
            foreach ($sensors as $sensor) {
                $device->setSensorData($sensor['type'], $sensor['data']);
            }
        }

        if ($device->isNormalDevice()) {
            $device->updateMcbStatus($extra);
        }

        if ($device->isChargingDevice()) {
            if (isset($extra['firmwareVersion']) && isset($extra['protocolVersion'])) {
                $device->setChargingData($extra);
            }

            if (is_array($extra['status'])) {
                $chargerID = $extra['chargerID'];
                $device->setChargerData($chargerID, $extra['status']);
            }

            if (is_array($extra['BMS'])) {
                $serial = $extra['serial'] ?? '';
                if ($serial) {
                    $chargerID = $extra['chargerID'];
                    $device->setChargerBMSData($chargerID, $extra['BMS']);
                }
            }

            if (is_array($extra['record'])) {
                $serial = $extra['serial'] ?? '';
                $chargerID = intval($extra['chargerID']);

                $res = Charging::settle($serial, $chargerID, $extra['record']);
                if (is_error($res)) {
                    Log::error('charging', [
                        'serial' => $serial,
                        'chargerID' => $chargerID,
                        'data' => $extra['record'],
                        'error' => $res,
                    ]);
                } else {
                    Log::info('charging', [
                        'data' => $extra,
                        'res' => $res,
                    ]);
                }
            }
        }

        $device->save();     
    }

    /**
     * v1版本 mcb::reload 事件处理
     * @param array $data
     */
    public static function onMcbReload(array $data = [])
    {
    }

    /**
     * v1版本 mcb::record 事件处理.
     *
     * @param array $data
     */
    public static function onMcbRecord(array $data = [])
    {
    }

    /**
     * v1版本 mcb::reset 事件处理.
     *
     * @param array $data
     */
    public static function onMcbReset(array $data = [])
    {
    }


    /**
     * v1版本 mcb::ping 事件处理.
     *
     * @param array $data
     */
    public static function onMcbPing(array $data = [])
    {
        $device = Device::get($data['uid'], true);
        if ($device) {
            $device->setMcbOnline(Device::ONLINE);
            $device->setLastPing(TIMESTAMP);
            $device->setLastOnline(TIMESTAMP);
            $device->save();
        }
    }

    public static function onMcbStartup(array $data = [])
    {
        $device = Device::get($data['uid'], true);
        if ($device) {
            if (isset($data['extra']['RSSI'])) {
                $device->setSig($data['extra']['RSSI']);
            }
            if (isset($data['extra']['ICCID'])) {
                $device->setICCID($data['extra']['ICCID']);
            }
            //重置设置锁
            $device->resetLock();

            $device->setMcbOnline(Device::ONLINE);
            $device->setLastOnline(TIMESTAMP);
            $device->setLastPing(TIMESTAMP);
            if ($device->isLastOnlineNotifyTimeout()) {
                Job::deviceOnlineNotify($device);
            }
            $device->save();
        }
    }
}
