<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace bluetooth\wx9se;

class BaseInfoCMD extends cmd
{

    public function __construct()
    {
        parent::__construct(0, protocol::CMD_QUERY, protocol::KEY_INFO);
    }
}