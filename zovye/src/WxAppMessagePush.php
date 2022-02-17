<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use wx\ErrorCode;
use wx\Prpcrypt;

class WxAppMessagePush
{
    const RESPONSE = 'success';
    const API_CUSTOMER_SERVICE_MESSAGE_SEND_URL = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token={ACCESS_TOKEN}';

    public static function verify(array $params = []): bool
    {
        if (empty($params['signature'])) {
            return false;
        }

        $data = [
            $params['timestamp'],
            $params['nonce'],
            Config::app('wxapp.message-push.token'),
        ];
    
        sort($data, SORT_STRING);

        return $params['signature'] === sha1(implode($data));
    }

    public static function handle(array $msg)
    {
        $wx_config = settings('agentWxapp', []);
        if (isEmptyArray($wx_config)) {
            return err('没有配置小程序！');
        }

        $config = Config::app('wxapp.message-push', []);
        if (!empty($msg['Encrypt'])) {
            list($ret, $decrypted) = (new Prpcrypt($config['encodingAESkey']))->decrypt($wx_config['key'], $msg['Encrypt']);

            if ($ret != ErrorCode::OK) {
                return err('消息解密失败！');
            }

            $msg = json_decode($decrypted, true);
        }

        if ($msg['MsgType'] == 'text') {
            $user = User::get($msg['FromUserName'], true, User::WxAPP);
            if (empty($user)) {
                return err('找不到指定用户！');
            }

            $data = $user->getLastActiveData();
            if (empty($data) || $data['from'] != 'wxapp' || empty($data['device']) || time() - $data['time'] > 600) {
                return err('请重新登录小程序！');
            }

            $device = Device::get($data['device']);
            if (empty($device)) {
                return err('找不到这个设备！');
            }

            $wx = Wx::getWxApp($wx_config);
            $access_token = $wx->getAccessToken();
            if (empty($access_token)) {
                return err('无法获取access_token');
            }

            $welcome_msg = [
                'touser' => $user->getOpenid(),
                'msgtype' => 'link',
                'link' => [
                    'title' => $config['msgTitle'] ?? '欢迎使用',
                    'description' => $config['msgDesc'] ?? '点击打开领取页面...',
                    'url' => $device->getUrl(),
                    'thumb_url' => $config['msgThumb'] ? Util::toMedia($config['msgThumb'], true) : '',
                ]
            ];

            $url = str_replace('{ACCESS_TOKEN}', $access_token, self::API_CUSTOMER_SERVICE_MESSAGE_SEND_URL);
            $result = Util::post($url, $welcome_msg);

            if (is_error($result)) {
                return $result;
            }

            if ($result['errcode']) {
                return err($result['errmsg'] ?? 'unknown error');
            }

            return $welcome_msg;
        }

        return true;
    }
}