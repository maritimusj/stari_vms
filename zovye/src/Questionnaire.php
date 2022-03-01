<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

class Questionnaire 
{
    public static function log($cond = [])
    {
        return m('account_logs')->where($cond);
    }
}