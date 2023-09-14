<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

class AgentApplication extends Base
{
    const WAIT = 0;
    const CHECKED = 1;

    public static function model(): base\modelFactory
    {
        return m('agent_app');
    }
}