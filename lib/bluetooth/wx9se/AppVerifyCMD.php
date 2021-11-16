<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace bluetooth\wx9se;

class AppVerifyCMD extends cmd
{
    public function __construct($data)
    {
        $data[] = 0;
        $data[] = 0;
        parent::__construct(0, protocol::CMD_SHAKE_HAND, protocol::KEY_VERIFY, $data);
    }

}