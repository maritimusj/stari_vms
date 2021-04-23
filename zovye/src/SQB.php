<?php


namespace zovye;


class SQB
{
    public static function activate($app_id, $vendor_sn, $vendor_key, $code)
    {
        $pay = new \SQB\pay([
            'sn' => $vendor_sn,
            'key' => $vendor_key,
            'app_id' => $app_id,
        ]);

        return  $pay->activate(Util::random(16, true), $code);
    }
}