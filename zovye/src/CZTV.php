<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

class CZTV
{
    public static function handle() {
        if (!App::isCZTVEnabled()) {
            return false;
        }

        $config = Config::cztv('client', []);
        if (empty($config['appid'])) {
           return false;
        }

        $token = request::str('token');
        if (empty($token)) {
            if ($config['redirect_url']) {
                Util::redirect($config['redirect_url']);
            } else {
                return false;
            }
        }

        return true;
    }
}