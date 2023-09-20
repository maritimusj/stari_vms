<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;

class JSON
{
    public static function data($data)
    {
        echo json_encode($data);
        exit();
    }
    /**
     * 向请求者返回JSON数据
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
            Response::json(true, ['msg' => $data]);
        }

        Response::json(true, $data);
    }

    /**
     * 向请求者返回失败的JSON数据，支持直接传入error结构，Exception对象等等
     * @param mixed $data
     */
    public static function fail($data = [])
    {
        if (is_string($data)) {
            Response::json(false, ['msg' => $data]);
        }

        if (is_error($data)) {
            Response::json(false, ['code' => $data['errno'], 'msg' => empty($data['message']) ? '操作失败！' : $data['message']]);
        }

        if ($data instanceof Exception) {
            Response::json(false, ['msg' => $data->getMessage()]);
        }

        Response::json(false, $data);
    }
}