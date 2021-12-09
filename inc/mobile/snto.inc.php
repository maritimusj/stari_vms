<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

if (App::isSNTOEnabled()) {
    Log::debug('snto', [
        'raw' => request::raw(),
    ]);

    if (request::has('app_id')) {
        $result = [
            'app_id' => request::str('app_id'),
            'order_id' => request::str('order_id'),
            'params' => request::str('params', '', true),
            'sign' => request::str('sign'),
        ];
    } else {
        parse_str(request::raw(), $result);
    }

    if ($result['app_id'] && $result['sign']) {
        SNTOAccount::cb($result);
        exit(SNTOAccount::RESPONSE_STR);
    } else {
        exit('数据异常！');
    }

}

exit('未启用！');