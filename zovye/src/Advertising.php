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
    const SPONSOR = 14; //赞助商轮播文字

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
        self::SPONSOR => 'sponsor',
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
        self::SPONSOR => '赞助商轮播文字',
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
        if (is_array($condition) && isset($condition['id'])) {
            return m('advertising')->where($condition);
        }

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
        $ad = self::get($id, $type);

        return $ad->destroy();
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

            $ad = self::query()->findOne($cond);
            if ($ad) {
                $cache[$ad->getId()] = $ad;

                return $ad;
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
            if ($obj->update() && self::setLastUpdate($obj->getType(), $obj->getUpdatetime())) {
                return true;
            }
        }

        return false;
    }

    public static function setLastUpdate($type, $ts = null): bool
    {
        $ts = $ts ?? time();

        return updateSettings("ads.version.type$type", $ts);
    }

    /**
     * 返回广告最后版本
     * @param $obj
     * @return mixed|null
     */
    public static function version($obj)
    {
        $type = $obj instanceof advertisingModelObj ? $obj->getType() : intval($obj);

        return settings("ads.version.type$type", 0);
    }

    public static function getMediaData(): array
    {
        return [
            'image' => [
                'icon' => 'fa-image',
                'title' => '图片',
            ],
            'video' => [
                'icon' => 'fa-youtube-play',
                'title' => '视频',
            ],
            'audio' => [
                'icon' => 'fa-music',
                'title' => '音频',
            ],
            'srt' => [
                'icon' => 'fa-text-width',
                'title' => '字幕',
            ],
        ];
    }

    public static function getWxData(): array
    {
        return [
            'image' => [
                'title' => '图片',
            ],
            'mpnews' => [
                'title' => '图文',
            ],
            'text' => [
                'title' => '文本',
            ],
        ];
    }

    public static function getNavData(): array
    {
        return [
            [
                'type' => Advertising::SCREEN,
                'title' => '设备屏幕',
            ],
            [
                'type' => Advertising::SCREEN_NAV,
                'title' => '屏幕引导图',
            ],
            [
                'type' => Advertising::WELCOME_PAGE,
                'title' => '关注页面',
            ],
            [
                'type' => Advertising::GET_PAGE,
                'title' => '领取页面',
            ],
            [
                'type' => Advertising::REDIRECT_URL,
                'title' => '网址转跳',
            ],
            [
                'type' => Advertising::PUSH_MSG,
                'title' => '消息推送',
            ],
            [
                'type' => Advertising::LINK,
                'title' => '链接推广',
            ],
            [
                'type' => Advertising::GOODS,
                'title' => '商品推荐',
            ],
            [
                'type' => Advertising::QRCODE,
                'title' => '推广二维码',
            ],
            [
                'type' => Advertising::PASSWD,
                'title' => '口令',
            ],
            [
                'type' => Advertising::WX_APP_URL_CODE,
                'title' => '小程序识别码',
            ],
        ];
    }

    /**
     * 格式化广告数据
     * @param advertisingModelObj $ad
     * @return array
     */
    public static function format(advertisingModelObj $ad): array
    {
        return [
            'id' => $ad->getId(),
            'title' => strval($ad->getTitle()),
            'type' => intval($ad->getType()),
            'state' => intval($ad->getState()),
            'extra' => $ad->getExtra(),
            'createtime' => intval($ad->getCreatetime()),
            'createtime_formatted' => date('Y-m-d H:i:s', $ad->getCreatetime()),
        ];
    }

    public static function createOrUpdate(
        agentModelObj $agent = null,
        advertisingModelObj $ad = null,
        $params = []
    ): array {
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
                $extra['size'] = intval($params['size']);
                $extra['clr'] = trim($params['clr']);
                $extra['background-clr'] = trim($params['background-clr']);
                $extra['speed'] = trim($params['speed']);
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
            $extra['app_id'] = trim($params['app_id']);
            $extra['app_path'] = trim($params['app_path']);

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

        } elseif ($type == Advertising::SPONSOR) {

            $extra['name'] = trim($params['name']);
            $extra['num'] = intval($params['num']);

        } else {
            $extra['url'] = trim($params['url']);
        }

        if (empty($ad)) {
            $data = [
                'state' => Advertising::NORMAL,
                'type' => $type,
                'title' => $title,
                'extra' => serialize($extra),
            ];

            if ($agent) {
                $data['agent_id'] = $agent->getAgentId();
            }

            $ad = Advertising::create($data);
            if (empty($ad)) {
                return err('创建失败！');
            }

        }

        $ad->setTitle($title);
        foreach ($extra as $key => $val) {
            $ad->setExtraData($key, $val);
        }

        if ($ad->save()) {
            //广告内容已变化
            $content_md5 = md5(http_build_query($extra));
            if (empty($ad->settings("reviewData.$content_md5"))) {
                $ad->updateSettings(
                    "reviewData.$content_md5",
                    [
                        'result' => ReviewResult::WAIT,
                        'adv' => [
                            'title' => $ad->getTitle(),
                            'type' => $ad->getType(),
                            'url' => $ad->getExtraData('url'),
                            'updatetime' => time(),
                        ],
                    ]
                );

                if ($ad->updateSettings('reviewData.current', $content_md5) && Advertising::update($ad)) {
                    Job::advReview($ad->getId());
                }
            } else {
                if ($ad->isReviewPassed()) {
                    //通知设备更新屏幕广告
                    if (in_array($ad->getType(), [Advertising::SCREEN, Advertising::SCREEN_NAV])) {
                        $assign_data = $ad->settings('assigned', []);
                        Advertising::notifyAll($assign_data);
                    }
                }
            }

            return ['msg' => empty($ad) ? '创建成功！' : '保存成功！'];
        }

        return err('保存失败！');
    }

    public static function getDeviceSliders(deviceModelObj $device): array
    {
        $slides = [];
        $ads = $device->getAds(Advertising::WELCOME_PAGE);
        foreach ($ads as $ad) {
            if ($ad['extra']['images']) {
                foreach ($ad['extra']['images'] as $image) {
                    if ($image) {
                        $slides[] = [
                            'id' => intval($ad['id']),
                            'name' => strval($ad['name']),
                            'image' => strval(Util::toMedia($image)),
                            'link' => strval($ad['extra']['link']),
                        ];
                    }
                }
            }
        }

        return $slides;
    }

    public static function pass($id, $admin = ''): bool
    {
        $ad = Advertising::get($id);
        if ($ad) {
            $current = $ad->settings('reviewData.current');
            if ($current) {
                $data = $ad->settings("reviewData.$current", []);
                $data['result'] = ReviewResult::PASSED;
                $data['reviewer'] = [
                    'username' => $admin,
                    'ip' => CLIENT_IP,
                    'time' => TIMESTAMP,
                ];

                if ($ad->updateSettings("reviewData.$current", $data) && Advertising::update($ad)) {

                    if (in_array($ad->getType(), [Advertising::SCREEN, Advertising::SCREEN_NAV])) {
                        //通知设备更新屏幕广告
                        $assign_data = $ad->settings('assigned', []);
                        Advertising::notifyAll($assign_data);
                    }

                    Job::advReviewResult($ad->getId());

                    return true;
                }
            }
        }

        return false;
    }

    public static function reject($id): bool
    {
        $ad = Advertising::get($id);
        if ($ad) {
            $unknown = 'unknown-hash-value';
            $current = $ad->settings('reviewData.current', $unknown);
            if ($current) {
                if ($current == $unknown) {
                    $ad->updateSettings('reviewData.current', $unknown);
                }
                if ($ad->updateSettings(
                        "reviewData.$current.result",
                        ReviewResult::REJECTED
                    ) && Advertising::update($ad)) {

                    Job::advReviewResult($ad->getId());

                    return true;
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
