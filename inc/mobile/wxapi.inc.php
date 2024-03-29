<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\wxApi;

defined('IN_IA') or exit('Access Denied');

use zovye\api\common;
use zovye\api\router;
use zovye\api\wx\ad;
use zovye\api\wx\agent;
use zovye\api\wx\alert;
use zovye\api\wx\article;
use zovye\api\wx\balance;
use zovye\api\wx\commission;
use zovye\api\wx\debug;
use zovye\api\wx\device;
use zovye\api\wx\fb;
use zovye\api\wx\goods;
use zovye\api\wx\group;
use zovye\api\wx\inventory;
use zovye\api\wx\keeper;
use zovye\api\wx\misc;
use zovye\api\wx\mp;
use zovye\api\wx\order;
use zovye\api\wx\promoter;
use zovye\api\wx\vip;
use zovye\api\wxweb\api;
use zovye\Request;

$op = ucfirst(Request::op('default'));

router::exec($op, [
    'DebugMode' => [debug::class, 'mode'],
    'DemoLogin' => [debug::class, 'login'],
    'UserLogin' => [common::class, 'login'],
    'FBPic' => [common::class, 'upload'],
    'Reg' => [agent::class, 'reg'],
    'PreLogin' => [agent::class, 'preLogin'],
    'Plugins' => [agent::class, 'pluginsList'],
    'Login' => [agent::class, 'login'],
    'LoginQR' => [agent::class, 'loginQR'],
    'LoginScan' => [agent::class, 'loginScan'],
    'LoginPoll' => [agent::class, 'loginPoll'],
    'AgentApplication' => [agent::class, 'application'],
    'SetAgentBank' => [agent::class, 'setAgentBank'],
    'GetAgentBank' => [agent::class, 'getAgentBank'],
    'DeviceList' => [agent::class, 'deviceList'],
    'DeviceUpdate' => [agent::class, 'deviceUpdate'],
    'DeviceInfo' => [agent::class, 'deviceInfo'],
    'DeviceBind' => [agent::class, 'deviceBind'],
    'DeviceOpen' => [agent::class, 'deviceTest'],
    'DeviceReset' => [agent::class, 'deviceReset'],
    'DeviceAssign' => [agent::class, 'deviceAssign'],
    'DeviceLowRemain' => [agent::class, 'deviceLowRemain'],
    'DeviceError' => [agent::class, 'deviceError'],
    'DeviceScheduleList' => [agent::class, 'DeviceScheduleList'],
    'DeviceScheduleCreate' => [agent::class, 'deviceScheduleCreate'],
    'DeviceScheduleRemove' => [agent::class, 'deviceScheduleRemove'],
    'OrderRefund' => [agent::class, 'orderRefund'],
    'Orders' => [agent::class, 'orderList'],
    'DeviceSetErrorCode' => [agent::class, 'deviceSetErrorCode'],
    'AgentSearch' => [agent::class, 'agentSearch'],
    'AgentUpdate' => [agent::class, 'agentUpdate'],
    'GetAgentKeepers' => [agent::class, 'getAgentKeepers'],
    'HomepageDefault' => [agent::class, 'homepageDefault'],
    'HomepageOrderStat' => [agent::class, 'homepageOrderStats'],
    'UpdateUserQRcode' => [agent::class, 'updateUserQRCode'],
    'GetUserQRcode' => [agent::class, 'getUserQRCode'],
    'AliAuthCode' => [agent::class, 'aliAuthCode'],
    'RemoveAgent' => [agent::class, 'removeAgent'],
    'AgentStat' => [agent::class, 'agentStats'],
    'UserIncome' => [agent::class, 'userIncome'],
    'AgentSub' => [agent::class, 'agentSub'],
    'SetAgentProfile' => [agent::class, 'setAgentProfile'],
    'GetAgentProfile' => [agent::class, 'getAgentProfile'],
    'GetKeeperDeviceList' => [agent::class, 'keeperDeviceList'],
    'Repair' => [agent::class, 'repair'],
    'AgentStats' => [agent::class, 'stats'],
    'DeviceSetNum' => [device::class, 'deviceReset'],
    'DeviceNearBy' => [device::class, 'deviceNearBy'],
    'DeviceOnline' => [device::class, 'getDeviceOnline'],
    'Statistics' => [device::class, 'statistics'],
    'AppRestart' => [device::class, 'appRestart'],
    'DeviceTypes' => [device::class, 'deviceTypes'],
    'DeleteDeviceTypes' => [device::class, 'deleteDeviceTypes'],
    'DeviceTypeDetail' => [device::class, 'deviceTypeDetail'],
    'UpdateDeviceTypes' => [device::class, 'updateDeviceTypes'],
    'GetDeviceInfo' => [device::class, 'getDeviceInfo'],
    'DeviceSub' => [device::class, 'deviceSub'],
    'DeviceOpenDoor' => [device::class, 'openDoor'],
    'DeviceKeepers' => [device::class, 'deviceKeepers'],
    'DeviceExpireAlertUpdate' => [alert::class, 'update'],
    'DeviceExpireAlertList' => [alert::class, 'list'],
    'UpdatePromoterConfig' => [promoter::class, 'updatePromoterConfig'],
    'GetPromoterConfig' => [promoter::class, 'getPromoterConfig'],
    'GetPromoterList' => [promoter::class, 'getPromoterList'],
    'GetPromoterLogs' => [promoter::class, 'getPromoterLogs'],
    'RemovePromoter' => [promoter::class, 'removePromoter'],
    'KeeperGetPromoterList' => [promoter::class, 'keeperGetPromoterList'],
    'KeeperGetPromoterLogs' => [promoter::class, 'keeperGetPromoterLogs'],
    'KeeperRemovePromoter' => [promoter::class, 'keeperRemovePromoter'],
    'SetKeeper' => [keeper::class, 'setKeeper'],
    'KeeperLogin' => [keeper::class, 'keeperLogin'],
    'DeleteKeeper' => [keeper::class, 'deleteKeeper'],
    'Keepers' => [keeper::class, 'keepers'],
    'RemoveDevicesFromKeeper' => [keeper::class, 'removeDevicesFromKeeper'],
    'AssignDevicesToKeeper' => [keeper::class, 'assignDevicesToKeeper'],
    'KeeperWithdraw' => [keeper::class, 'keeperWithdraw'],
    'GetKeeperBank' => [keeper::class, 'getKeeperBank'],
    'SetKeeperBank' => [keeper::class, 'setKeeperBank'],
    'KeeperBrief' => [keeper::class, 'brief'],
    'KeeperDeviceList' => [keeper::class, 'deviceList'],
    'KeeperBalanceLog' => [keeper::class, 'balanceLog'],
    'KeeperLowDevices' => [keeper::class, 'lowDevices'],
    'KeeperErrorDevices' => [keeper::class, 'errorDevices'],
    'KeeperDeviceDetail' => [keeper::class, 'deviceDetail'],
    'KeeperDeviceReset' => [keeper::class, 'deviceReset'],
    'KeeperDeviceTest' => [keeper::class, 'deviceTest'],
    'KeeperStats' => [keeper::class, 'stats'],
    'KeeperOrders' => [keeper::class, 'getOrders'],
    'ViewKeeperStats' => [keeper::class, 'viewKeeperStats'],
    'KeeperOrderRefund' => [keeper::class, 'orderRefund'],
    'KeeperUserStats' => [keeper::class, 'userStats'],
    'KeeperUserCommissionStats' => [keeper::class, 'commissionStats'],
    'MpDetail' => [mp::class, 'detail'],
    'MpAssign' => [mp::class, 'assign'],
    'Mpupload' => [mp::class, 'upload'],
    'Mpaccounts' => [mp::class, 'accounts'],
    'Mpban' => [mp::class, 'ban'],
    'Mpdelete' => [mp::class, 'delete'],
    'Mpsave' => [mp::class, 'save'],
    'MpGroupAssign' => [mp::class, 'groupAssign'],
    'MpAuthUrl' => [mp::class, 'mpAuthUrl'],
    "MpDouyinAuthQRCode" => [mp::class, 'getDouyinAuthQRCode'],
    "MpDouyinAuthResult" => [mp::class, 'getDouyinAuthResult'],
    'AdvAssign' => [ad::class, 'assign'],
    'Advs' => [ad::class, 'list'],
    'AdvsCreate' => [ad::class, 'createOrUpdate'],
    'AdvsUpdate' => [ad::class, 'createOrUpdate'],
    'AdvDelete' => [ad::class, 'delete'],
    'UploadFile' => [ad::class, 'uploadFile'],
    'AdvGroupAssign' => [ad::class, 'groupAssign'],
    'AdvGetBonus' => [api::class, 'reward'],
    'ArticleDetail' => [article::class, 'detail'],
    'Article' => [article::class, 'list'],
    'Archive' => [article::class, 'archive'],
    'Faq' => [article::class, 'faq'],
    'BalanceBrief' => [balance::class, 'brief'],
    'BalanceWithdraw' => [balance::class, 'withdraw'],
    'BalanceLog' => [balance::class, 'log'],
    'UserBalanceLog' => [balance::class, 'userBalanceLog'],
    'OrderDetail' => [order::class, 'detail'],
    'OrderDefault' => [order::class, 'default'],
    'OrderExportHeaders' => [order::class, 'getOrderExportHeaders'],
    'OrderExportDo' => [order::class, 'orderExportDo'],
    'CommissionSharedAccount' => [commission::class, 'sharedAccount'],
    'CommissionAccountAssign' => [commission::class, 'accountAssign'],
    'CommissionPtAgreement' => [commission::class, 'ptAgreement'],
    'SetCommissionLevel' => [commission::class, 'level'],
    'MonthStat' => [commission::class, 'monthStats'],
    'ChargingStats' => [commission::class, 'chargingStats'],
    'ChargingMonthStats' => [commission::class, 'chargingMonthStats'],
    'GetGoodsList' => [goods::class, 'list'],
    'GoodsDetail' => [goods::class, 'detail'],
    'DeleteGoods' => [goods::class, 'delete'],
    'Goods' => [goods::class, 'create'],
    'GroupList' => [group::class, 'list'],
    'GroupDetail' => [group::class, 'detail'],
    'GroupCreate' => [group::class, 'create'],
    'GroupUpdate' => [group::class, 'update'],
    'GroupDelete' => [group::class, 'delete'],
    'FeedBack' => [fb::class, 'feedback'],
    'DeviceStats' => [misc::class, 'deviceStats'],
    'OrderStats' => [misc::class, 'orderStats'],
    'InventoryGoods' => [inventory::class, 'list'],
    'InventoryLogs' => [inventory::class, 'logs'],
    'VipUserInfo' => [vip::class, 'userInfo'],
    'VipCreate' => [vip::class, 'create'],
    'VipRemove' => [vip::class, 'remove'],
    'VipList' => [vip::class, 'getList'],
    'VipDevice' => [vip::class, 'updateDeviceIds'],
    'VipDeviceRenewal' => [vip::class, 'payForDeviceRenewal'],
]);

