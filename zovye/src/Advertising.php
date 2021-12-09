<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\base\modelObjFinder;
use zovye\model\agentModelObj;
use zovye\model\deviceModelObj;
use zovye\model\advertisingModelObj;

class Advertising extends State
{
    const NORMAL = 0; //正常
    const BANNED = 1; //已禁用
    const DELETED = -1; //已删除

    //广告类型
    const UNKNOWN = 0; //未确定
    const SCREEN = 1; //设备屏幕
    const SCREEN_NAV = 2; //设备屏幕固定引导图
    const ACCOUNT = 3; //公众号
    const WELCOME_PAGE = 4; //关注页面
    const GET_PAGE = 5; //领货页面
    const REDIRECT_URL = 6; //网址转跳
    const PUSH_MSG = 7; //消息推送
    const ACTIVE_QRCODE = 8; //活码
    const LINK = 9; //推广链接
    const GOODS = 10; //商品推荐
    const QRCODE = 11; //推广二维码
    const PASSWD = 12; //用于推广的口令
    const WX_APP_URL_CODE = 13; //微信小程序URL识别码

    public static $names = [
        self::UNKNOWN => 'default',
        self::SCREEN => 'screen',
        self::ACCOUNT => 'account',
        self::SCREEN_NAV => 'screen_nav',
        self::WELCOME_PAGE => 'slides',
        self::GET_PAGE => 'slides',
        self::REDIRECT_URL => 'redirect_url',
        self::PUSH_MSG => 'push_msg',
        self::LINK => 'link',
        self::GOODS => 'goods',
        self::QRCODE => 'qrcode',
        self::PASSWD => 'passwd',
        self::WX_APP_URL_CODE => 'wx_app_url_code',
    ];

    protected static $title = [
        self::SCREEN => '设备屏幕',
        self::ACCOUNT => '公众号',
        self::SCREEN_NAV => '屏幕引导图',
        self::WELCOME_PAGE => '关注页面',
        self::GET_PAGE => '领货页面',
        self::REDIRECT_URL => '网址转跳',
        self::PUSH_MSG => '消息推送',
        self::ACTIVE_QRCODE => '活码广告',
        self::LINK => '推广链接',
        self::GOODS => '商品推荐',
        self::QRCODE => '推广二维码',
        self::PASSWD => '口令',
        self::WX_APP_URL_CODE => '微信小程序识别码',
    ];

    /**
     * @param array $data
     * @return null|advertisingModelObj
     */
    public static function create(array $data = []): ?advertisingModelObj
    {
        if (empty($data['uniacid'])) {
            $data['uniacid'] = We7::uniacid();
        }

        return m('advertising')->create($data);
    }

    /**
     * @param mixed $condition
     * @return modelObjFinder
     */
    public static function query($condition = []): modelObjFinder
    {
        return m('advertising')->where(We7::uniacid([]))->where($condition);
    }

    /**
     * @param $cond
     * @return advertisingModelObj|null
     */
    public static function findOne($cond): ?advertisingModelObj
    {
        return self::query($cond)->findOne();
    }

    public static function remove($id, $type = null): bool
    {
        $entry = self::get($id, $type);
        return $entry->destroy();
    }

    /**
     * @param int $id
     * @param null $type
     * @return advertisingModelObj|null
     */
    public static function get(int $id, $type = null): ?advertisingModelObj
    {
        static $cache = [];
        if ($id) {
            if ($cache[$id]) {
                return $cache[$id];
            }
            $cond = ['id' => $id, 'state <>' => self::DELETED];
            if ($type) {
                $cond['type'] = $type;
            }

            $res = self::query()->findOne($cond);
            if ($res) {
                $cache[$res->getId()] = $res;
                return $res;
            }            
        }

        return null;
    }

    public static function getTypeId($name)
    {
        return array_search(strtolower($name), self::$names);
    }

    /**
     * 更新广告版本
     * @param $obj
     * @return bool
     */
    public static function update($obj): bool
    {
        if ($obj instanceof advertisingModelObj) {
            //广告变动了
            if ($obj->update() && self::setAdvsLastUpdate($obj->getType(), $obj->getUpdatetime())) {
                return true;
            }
        }

        return false;
    }

    public static function setAdvsLastUpdate($type, $ts = null): bool
    {
        $ts = $ts ?? time();
        return updateSettings("advs.version.type$type", $ts);
    }

    /**
     * 返回广告最后版本
     * @param $obj
     * @return mixed|null
     */
    public static function version($obj)
    {
        $type = $obj instanceof advertisingModelObj ? $obj->getType() : intval($obj);
        return settings("advs.version.type$type", 0);
    }

    /**
     * 格式化广告数据
     * @param advertisingModelObj $adv
     * @return array
     */
    public static function format(advertisingModelObj $adv): array
    {
        return [
            'id' => $adv->getId(),
            'title' => strval($adv->getTitle()),
            'type' => intval($adv->getType()),
            'state' => intval($adv->getState()),
            'extra' => $adv->getExtra(),
            'createtime' => intval($adv->getCreatetime()),
            'createtime_formatted' => date('Y-m-d H:i:s', $adv->getCreatetime()),
        ];
    }

    public static function createOrUpdate(agentModelObj $agent = null, advertisingModelObj $adv = null, $params = []): array
    {
        $type = intval($params['type']);
        if (!Advertising::has($type)) {
            return err('广告类型不正确！');
        }

        $title = trim($params['title']);
        if (empty($title)) {
            return err('请填写广告名称！');
        }

        $extra = [];

        if ($type == Advertising::SCREEN) {
            $extra['media'] = $params['media'];

            if (!Media::has($extra['media'])) {
                return err('不正确的广告类型！');
            }

            if (in_array($extra['media'], [Media::IMAGE, Media::VIDEO, Media::SRT])) {
                $extra['area'] = intval($params['area']);
            }

            if ($extra['media'] == Media::SRT) {
                $extra['text'] = trim($params['text']);
                if (empty($extra['text'])) {
                    return err('请指定字幕文字内容！');
                }
            } else {
                $url = $params['url'];
                if (empty($url)) {
                    return err('请指定广告内容或文件！');
                }

                $extra['url'] = $url;

                if ($extra['media'] == Media::IMAGE) {
                    $extra['duration'] = intval($params['duration']) ?: DEFAULT_IMAGE_DURATION;
                }
            }

        } elseif ($type == Advertising::SCREEN_NAV) {

            $url = $params['url'];
            if (empty($url)) {
                return err('没有指定图片文件！');
            }

            $extra['url'] = $url;

        } elseif (in_array($type, [Advertising::WELCOME_PAGE, Advertising::GET_PAGE])) {

            $images = (array)$params['images'];
            if (empty($images)) {
                return err('没有指定广告图片文件！');
            }

            $extra['images'] = $images;
            $extra['link'] = trim($params['link']);

            if ($type == Advertising::WELCOME_PAGE) {
                $extra['app_id'] = trim($params['app_id']);
                $extra['app_path'] = trim($params['app_path']);
            }

        } elseif ($type == Advertising::REDIRECT_URL) {

            $extra['url'] = trim($params['url']);
            $extra['delay'] = intval($params['delay']);
            $extra['when'] = [
                'success' => empty($params['when_success']) ? 0 : 1,
                'fail' => empty($params['when_fail']) ? 0 : 1,
            ];

        } elseif ($type == Advertising::PUSH_MSG) {

            $extra['msg'] = [
                'type' => trim($params['pushAccountMsg_type']),
                'val' => trim($params['pushAccountMsg_val']),
            ];
            $extra['delay'] = intval($params['pushAccountMsg_delay']);

        } elseif ($type == Advertising::LINK) {

            $extra['url'] = trim($params['link']);
            $extra['app_id'] = trim($params['app_id']);
            $extra['app_path'] = trim($params['app_path']);

            $images = (array)$params['images'];
            if (!empty($images[0])) {
                $extra['image'] = strval($images[0]);
            }

        } elseif ($type == Advertising::GOODS) {
            $images = (array)$params['images'];
            if (!empty($images[0])) {
                $extra['image'] = strval($images[0]);
            }

            $extra['url'] = trim($params['link']);
            $extra['price'] = trim($params['price']);
            $extra['discount_price'] = trim($params['discount_price']);
            $extra['app_id'] = trim($params['app_id']);
            $extra['app_path'] = trim($params['app_path']);

        } elseif ($type == Advertising::QRCODE) {

            $extra['text'] = trim($params['text']);
            $extra['image'] = trim($params['image']);

        } elseif ($type == Advertising::PASSWD) {

            $extra['code'] = trim($params['code']);
            $extra['text'] = trim($params['text']);

        } elseif ($type == Advertising::WX_APP_URL_CODE) {

            $extra['code'] = trim($params['code']);

        } else {
            $extra['url'] = trim($params['url']);
        }

        if (empty($adv)) {
            $data = [
                'state' => Advertising::NORMAL,
                'type' => $type,
                'title' => $title,
                'extra' => serialize($extra),
            ];

            if ($agent) {
                $data['agent_id'] = $agent->getAgentId();
            }

            $adv = Advertising::create($data);
            if (empty($adv)) {
                return err('创建失败！');
            }

        }

        $adv->setTitle($title);
        foreach ($extra as $key => $val) {
            $adv->setExtraData($key, $val);
        }

        if ($adv->save()) {
            //广告内容已变化
            $content_md5 = md5(http_build_query($extra));
            if (empty($adv->settings("reviewData.$content_md5"))) {
                $adv->updateSettings(
                    "reviewData.$content_md5",
                    [
                        'result' => ReviewResult::WAIT,
                        'adv' => [
                            'title' => $adv->getTitle(),
                            'type' => $adv->getType(),
                            'url' => $adv->getExtraData('url'),
                            'updatetime' => time(),
                        ],
                    ]
                );

                if ($adv->updateSettings('reviewData.current', $content_md5) && Advertising::update($adv)) {
                    Job::advReview($adv->getId());
                }
            } else {
                if ($adv->isReviewPassed()) {
                    //通知设备更新屏幕广告
                    if (in_array($adv->getType(), [Advertising::SCREEN, Advertising::SCREEN_NAV])) {
                        $assign_data = $adv->settings('assigned', []);
                        Advertising::notifyAll($assign_data, []);
                    }
                }
            }

            return ['msg' => empty($adv) ? '创建成功！' : '保存成功！'];
        }

        return err('保存失败！');
    }

    public static function getDeviceSliders(deviceModelObj $device): array
    {
        $slides = [];
        $advs = $device->getAdvs(Advertising::WELCOME_PAGE);
        foreach ($advs as $adv) {
            if ($adv['extra']['images']) {
                foreach ($adv['extra']['images'] as $image) {
                    if ($image) {
                        $slides[] = [
                            'id' => intval($adv['id']),
                            'name' => strval($adv['name']),
                            'image' => strval(Util::toMedia($image)),
                            'link' => strval($adv['extra']['link']),
                        ];
                    }
                }
            }
        }
        return $slides;
    }

    public static function pass($id, $admin = ''): bool
    {  
        if ($id > 0) {
            $adv = Advertising::get($id);
            if ($adv) {
                $current = $adv->settings('reviewData.current');
                if ($current) {
                    $data = $adv->settings("reviewData.$current", []);
                    $data['result'] = ReviewResult::PASSED;
                    $data['reviewer'] = [
                        'username' => $admin,
                        'ip' => CLIENT_IP,
                        'time' => TIMESTAMP,
                    ];
    
                    if ($adv->updateSettings("reviewData.$current", $data) && Advertising::update($adv)) {
    
                        if (in_array($adv->getType(), [Advertising::SCREEN, Advertising::SCREEN_NAV])) {
                            //通知设备更新屏幕广告
                            $assign_data = $adv->settings('assigned', []);
                            Advertising::notifyAll($assign_data, []);
                        }
    
                        Job::advReviewResult($adv->getId());
                        return true;
                    }
                }
            }
        }    
        return false;
    }

    public static function reject($id): bool
    {
        if ($id > 0) {
            $adv = Advertising::get($id);
            if ($adv) {
                $unknown = 'unknown-hash-value';
                $current = $adv->settings('reviewData.current', $unknown);
                if ($current) {
                    if ($current == $unknown) {
                        $adv->updateSettings('reviewData.current', $unknown);
                    }
                    if ($adv->updateSettings("reviewData.$current.result", ReviewResult::REJECTED) && Advertising::update($adv)) {
                        Job::advReviewResult($adv->getId());
                        return true;
                    }
                }
            }
        }
    
        return false;
    }

    public static function notifyAll(array $origin_data, array $data = []): bool
    {
        return CtrlServ::appNotifyAll($origin_data, $data);
    }
}
