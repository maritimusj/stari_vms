<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

class FlashEgg
{
    const DEBUG_API_URL = 'https://test-sdi-api.newposm.com/device/api/v1/triggerAdPlay';
    const PRO_API_URL = 'https://sdi-api.newposm.com/device/api/v1/triggerAdPlay';

    protected $app_key = '08171593-81be-f859-047d-97923081ca0d';

    protected $debug = false;

    protected function sign($params = []): string
    {
        return md5(implode($params).$this->app_key);
    }

    public function setDebugMode()
    {
        $this->debug = true;
    }

    public function triggerAdPlay($uid, $no)
    {
        $res = Util::post($this->debug ? self::DEBUG_API_URL : self::PRO_API_URL, [
            'devMac' => $uid,
            'triggerNo' => $no,
            'sign' => $this->sign([$uid, $no]),
        ]);

        if (empty($res)) {
            return err('请求API接口失败！');
        }

        if (is_error($res)) {
            return $res;
        }

        if ($res['code'] != 200) {
            return err($res['msg'] ?? '发生错误！');
        }

        return true;
    }
}