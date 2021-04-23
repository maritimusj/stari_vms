<?php


namespace zovye;


use Exception;

class JSON
{
    public static function success($data = [])
    {
        if (is_string($data)) {
            Util::resultJSON(true, ['msg' => $data]);
        }

        Util::resultJSON(true, $data);
    }

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