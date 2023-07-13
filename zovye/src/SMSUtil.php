<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

use we7\ihttp;

class SMSUtil
{
    /**
     * 发送短信
     *
     * @param $mobile
     * @param $tpl_id
     * @param $msg
     *
     * @return bool|array
     */
    public static function send($mobile, $tpl_id, $msg)
    {
        $config = settings('notice.sms', []);

        if ($config['url'] && $config['appkey']) {
            $tpl_value = '';

            if (is_string($msg)) {
                $tpl_value = $msg;
            } elseif (is_array($msg)) {
                $arr = [];
                foreach ($msg as $key => $value) {
                    $arr[] = "#$key#=".urlencode($value);
                }

                $tpl_value = implode('&', $arr);
            }

            $res = ihttp::post($config['url'], [
                'mobile' => $mobile,
                'tpl_id' => $tpl_id,
                'tpl_value' => urlencode($tpl_value),
                'key' => $config['appkey'],
            ]);

            if ($res['code'] == 200) {
                $result = json_decode($res['content'], true);
                if ($result['error_code'] === 0) {
                    return true;
                }

                return err($result['reason']);
            }
        }

        return err('请先配置短信接口！');
    }
}