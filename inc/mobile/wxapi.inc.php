<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\wxApi;

defined('IN_IA') or exit('Access Denied');

use zovye\api\router;
use zovye\request;
use zovye\Util;

Util::extraAjaxJsonData();

$op = ucfirst(request::op('default'));

router::exec($op, [
    'DebugMode' => '\zovye\api\wx\debug::mode',
    'DemoLogin' => '\zovye\api\wx\debug::login',

    'DeviceSetNum' => '@\zovye\api\wx\device::deviceReset',

    'Reg' => '\zovye\api\wx\agent::reg',
    'PreLogin' => '\zovye\api\wx\agent::preLogin',
    'Plugins' => '\zovye\api\wx\agent::pluginsList',
    'Login' => '\zovye\api\wx\agent::login',
    'LoginQR' => '\zovye\api\wx\agent::loginQR',
    'LoginScan' => '\zovye\api\wx\agent::loginScan',
    'LoginPoll' => '\zovye\api\wx\agent::loginPoll',
    'UserLogin' => '\zovye\api\wxx\common::login',

    'AgentApplication' => '\zovye\api\wx\agent::application',
    'AgentMsg' => '\zovye\api\wx\agent::agentMsg',
    'SetAgentBank' => '\zovye\api\wx\agent::setAgentBank',
    'GetAgentBank' => '\zovye\api\wx\agent::getAgentBank',
    'AgentMsgDetail' => '\zovye\api\wx\agent::msgDetail',
    'AgentMsgRemove' => '\zovye\api\wx\agent::msgRemove',
    'DeviceList' => '\zovye\api\wx\agent::deviceList',
    'DeviceUpdate' => '@\zovye\api\wx\agent::deviceUpdate',
    'DeviceInfo' => '\zovye\api\wx\agent::deviceInfo',
    'DeviceBind' => '@\zovye\api\wx\agent::deviceBind',
    'DeviceOpen' => '\zovye\api\wx\agent::deviceTest',
    'DeviceReset' => '@\zovye\api\wx\agent::deviceReset',
    'DeviceAssign' => '@\zovye\api\wx\agent::deviceAssign',
    'DeviceLowRemain' => '\zovye\api\wx\agent::deviceLowRemain',
    'DeviceError' => '\zovye\api\wx\agent::deviceError',
    'OrderRefund' => '\zovye\api\wx\agent::orderRefund',
    'Orders' => '\zovye\api\wx\agent::orders',
    'DeviceSetErrorCode' => '\zovye\api\wx\agent::deviceSetErrorCode',
    'AgentSearch' => '\zovye\api\wx\agent::agentSearch',
    'AgentUpdate' => '\zovye\api\wx\agent::agentUpdate',
    'GetAgentKeepers' => '\zovye\api\wx\agent::getAgentKeepers',
    'HomepageDefault' => '\zovye\api\wx\agent::homepageDefault',
    'HomepageOrderStat' => '\zovye\api\wx\agent::homepageOrderStat',
    'UpdateUserQRcode' => '\zovye\api\wx\agent::updateUserQRCode',
    'GetUserQRcode' => '\zovye\api\wx\agent::getUserQRCode',
    'AliAuthCode' => '\zovye\api\wx\agent::aliAuthCode',
    'RemoveAgent' => '\zovye\api\wx\agent::removeAgent',
    'AgentStat' => '\zovye\api\wx\agent::agentStat',
    'UserIncome' => '\zovye\api\wx\agent::userIncome',
    'AgentSub' => '\zovye\api\wx\agent::agentSub',
    'SetAgentProfile' => '\zovye\api\wx\agent::setAgentProfile',
    'GetAgentProfile' => '\zovye\api\wx\agent::getAgentProfile',
    'GetKeeperDeviceList' => '\zovye\api\wx\agent::keeperDeviceList',
    'Repair' => '\zovye\api\wx\agent::repair',

    'DeviceOnline' => '\zovye\api\wx\device::getDeviceOnline',
    'DeviceGoods' => '\zovye\api\wx\device::deviceGoods',
    'Statistics' => '\zovye\api\wx\device::statistics',
    'AppRestart' => '\zovye\api\wx\device::appRestart',
    'DeviceTypes' => '\zovye\api\wx\device::deviceTypes',
    'DeleteDeviceTypes' => '\zovye\api\wx\device::deleteDeviceTypes',
    'DeviceTypeDetail' => '\zovye\api\wx\device::deviceTypeDetail',
    'UpdateDeviceTypes' => '\zovye\api\wx\device::updateDeviceTypes',
    'GetDeviceInfo' => '\zovye\api\wx\device::getDeviceInfo',
    'DeviceSub' => '\zovye\api\wx\device::deviceSub',

    'DeviceOpenDoor' => '\zovye\api\wx\device::openDoor',
    'DeviceKeepers' => '\zovye\api\wx\device::deviceKeepers',

    'SetKeeper' => '\zovye\api\wx\keeper::setKeeper',
    'KeeperLogin' => '\zovye\api\wx\keeper::keeperLogin',
    'DeleteKeeper' => '\zovye\api\wx\keeper::deleteKeeper',
    'Keepers' => '\zovye\api\wx\keeper::keepers',
    'RemoveDevicesFromKeeper' => '\zovye\api\wx\keeper::removeDevicesFromKeeper',
    'AssignDevicesToKeeper' => '\zovye\api\wx\keeper::assignDevicesToKeeper',
    'KeeperWithdraw' => '\zovye\api\wx\keeper::keeperWithdraw',
    'GetKeeperBank' => '\zovye\api\wx\keeper::getKeeperBank',
    'SetKeeperBank' => '\zovye\api\wx\keeper::setKeeperBank',
    'KeeperBrief' => '\zovye\api\wx\keeper::brief',
    'KeeperDeviceList' => '\zovye\api\wx\keeper::deviceList',
    'KeeperBalanceLog' => '\zovye\api\wx\keeper::balanceLog',
    'KeeperLowDevices' => '\zovye\api\wx\keeper::lowDevices',
    'KeeperErrorDevices' => '\zovye\api\wx\keeper::errorDevices',
    'KeeperDeviceDetail' => '\zovye\api\wx\keeper::deviceDetail',
    'KeeperDeviceReset' => '@\zovye\api\wx\keeper::deviceReset',
    'KeeperDeviceTest' => '\zovye\api\wx\keeper::deviceTest',
    'KeeperStats' => '\zovye\api\wx\keeper::stats',
    'ViewKeeperStats' => '\zovye\api\wx\keeper::viewKeeperStats',

    'MpDetail' => '\zovye\api\wx\mp::detail',
    'MpAssign' => '\zovye\api\wx\mp::assign',
    'Mpupload' => '\zovye\api\wx\mp::upload',
    'Mpaccounts' => '\zovye\api\wx\mp::accounts',
    'Mpban' => '\zovye\api\wx\mp::ban',
    'Mpdelete' => '\zovye\api\wx\mp::delete',
    'Mpsave' => '\zovye\api\wx\mp::save',
    'MpGroupAssign' => '\zovye\api\wx\mp::groupAssign',
    'MpAuthUrl' => '\zovye\api\wx\mp::mpAuthUrl',
    "MpDouyinAuthQRCode" => '\zovye\api\wx\mp::getDouyinAuthQRCode',
    "MpDouyinAuthResult" => '\zovye\api\wx\mp::getDouyinAuthResult',

    'AdvAssign' => '\zovye\api\wx\adv::assign',
    'Advs' => '\zovye\api\wx\adv::list',
    'AdvsCreate' => '\zovye\api\wx\adv::createOrUpdate',
    'AdvsUpdate' => '\zovye\api\wx\adv::createOrUpdate',
    'AdvDelete' => '\zovye\api\wx\adv::delete',
    'UploadFile' => '\zovye\api\wx\adv::uploadFile',
    'AdvGroupAssign' => '\zovye\api\wx\adv::groupAssign',

    'AdvGetBonus' => '\zovye\api\wxweb\api::reward',

    'ArticleDetail' => '\zovye\api\wx\article::detail',
    'Article' => '\zovye\api\wx\article::list',
    'Archive' => '\zovye\api\wx\article::archive',
    'Faq' => '\zovye\api\wx\article::faq',

    'BalanceBrief' => '\zovye\api\wx\balance::brief',
    'BalanceWithdraw' => '\zovye\api\wx\balance::withdraw',
    'BalanceLog' => '\zovye\api\wx\balance::log',
    'UserBalanceLog' => '\zovye\api\wx\balance::userBalanceLog',

    'OrderDetail' => '\zovye\api\wx\order::detail',
    'OrderDefault' => '\zovye\api\wx\order::default',
    'OrderGetIds' => '\zovye\api\wx\order::getExportIds',
    'OrderExport' => '\zovye\api\wx\order::export',

    'CommissionSharedAccount' => '\zovye\api\wx\commission::sharedAccount',
    'CommissionAccountAssign' => '\zovye\api\wx\commission::accountAssign',
    'CommissionPtAgreement' => '\zovye\api\wx\commission::ptAgreement',
    'SetCommissionLevel' => '\zovye\api\wx\commission::level',
    'MonthStat' => '\zovye\api\wx\commission::monthStat',

    'GetGoodsList' => '\zovye\api\wx\goods::list',
    'GoodsDetail' => '\zovye\api\wx\goods::detail',
    'DeleteGoods' => '\zovye\api\wx\goods::delete',
    'Goods' => '\zovye\api\wx\goods::create',

    'GroupList' => '\zovye\api\wx\group::list',
    'GroupDetail' => '\zovye\api\wx\group::detail',
    'GroupCreate' => '\zovye\api\wx\group::create',
    'GroupUpdate' => '\zovye\api\wx\group::update',
    'GroupDelete' => '\zovye\api\wx\group::delete',

    'MaintainRecord' => '\zovye\api\wx\maintenance::record',
    'KeeperMaintainRecord' => '\zovye\api\wx\maintenance::keeperMaintainRecord',
    'MRList' => '\zovye\api\wx\maintenance::MRList',
    'KeeperMRList' => '\zovye\api\wx\maintenance::keeperMRList',

    'FBPic' => '\zovye\api\wx\fb::pic',
    'FeedBack' => '\zovye\api\wx\fb::feedback',

    'YZShopStats' => '\zovye\api\wx\yzshop::stats',
    'News' => '\zovye\api\wx\yzshop::news',

    'DeviceStats' => '*\zovye\api\wx\misc::deviceStats',
    'OrderStats' => '\zovye\api\wx\misc::orderStats',

    'ChargingStats' => '\zovye\api\wx\commission::chargingStats',
    'ChargingMonthStats' => '\zovye\api\wx\commission::chargingMonthStats',

    'InventoryGoods' => '\zovye\api\wx\inventory::list',
    'InventoryLogs' => '\zovye\api\wx\inventory::logs',

    'vipCreate' => '\zovye\api\wx\vip::create',
    'vipRemove' => '\zovye\api\wx\vip::remove',
    'vipList' => '\zovye\api\wx\vip::getList',
    'vipDevice' => '\zovye\api\wx\vip::updateDeviceIds',

]);

