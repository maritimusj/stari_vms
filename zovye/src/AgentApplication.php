<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

class AgentApplication
{
    public static function model(): base\modelFactory
    {
        return m('agent_app');
    }
}