<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

class Article extends Base
{
    public static function model(): base\modelFactory
    {
        return m('article');
    }
}