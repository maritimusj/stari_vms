<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$result = DBUtil::transactionDo(function() {
    $id = Request::int('id');

    $account = Account::get($id);

    if ($account) {
        if ($account->isThirdPartyPlatform()) {
            return err('无法删除这个任务！');
        }

        if ($account->isFlashEgg()) {
            $goods = $account->getGoods();
            if ($goods) {
                if (!Goods::safeDelete($goods)) {
                    return err('删除关联商品失败！');
                }
            }
        }

        $title = $account->getTitle();
        $account->destroy();
        Account::updateAccountData();

        return ['title' => $title];
    }

    return err('找不到这个任务！');
});

if (is_error($result)) {
    Response::toast('删除失败！', $this->createWebUrl('account'), 'error');
}

Response::toast("删除任务{$result['title']}成功！", $this->createWebUrl('account'), 'success');