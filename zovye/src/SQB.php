<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\util\Util;

class SQB
{
    const RESPONSE = 'success';

    public static function activate($app_id, $vendor_sn, $vendor_key, $code)
    {
        $pay = new \SQB\pay([
            'sn' => $vendor_sn,
            'key' => $vendor_key,
            'app_id' => $app_id,
        ]);

        return $pay->activate(Util::random(16, true), $code);
    }
}