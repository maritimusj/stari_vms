<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;


use Exception;

class JSON
{
    /**
     * 向请求者返回JSON数据
     * @param $data
     */
    public static function result($data)
    {
        if (is_error($data) || $data instanceof Exception) {
            self::fail($data);
        }
        self::success($data);
    }

    /**
     * 向请求者返回成功的JSON数据
     * @param mixed $data
     */
    public static function success($data = [])
    {
        if (is_string($data)) {
            Util::resultJSON(true, ['msg' => $data]);
        }

        Util::resultJSON(true, $data);
    }

    /**
     * 向请求者返回失败的JSON数据，支持直接传入error结构，Exception对象等等
     * @param mixed $data
     */
    public static function fail($data = [])
    {
        if (is_string($data)) {
            Util::resultJSON(false, ['msg' => $data]);
        }

        if (is_error($data)) {
            Util::resultJSON(false, ['msg' => empty($data['message']) ? '操作失败！' : $data['message']]);
        }

        if ($data instanceof Exception) {
            Util::resultJSON(false, ['msg' => $data->getMessage()]);
        }

        Util::resultJSON(false, $data);
    }
}