<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\Account;
use zovye\App;
use zovye\base\modelObj;
use zovye\base\modelObjFinder;
use zovye\State;
use zovye\traits\ExtraDataGettersAndSetters;
use zovye\Util;
use zovye\WxPlatform;

use function zovye\m;
use function zovye\tb;

/**
 * Class accountModelObj
 * @method int getAgentId()
 * @method setAgentId($agent_id)
 * @method string getUid()
 * @method setUid($uid)
 * @method string getName()
 * @method setName($name)
 * @method setTitle($title)
 * @method string getImg()
 * @method setImg($img)
 * @method string getQrcode()
 * @method setQrcode($qrcode)
 * @method string getClr()
 * @method setClr($clr)
 * @method int getCount()
 * @method setCount($count)
 * @method int getSccount()
 * @method setSccount($count)
 * @method string getScname()
 * @method setScname($name)
 * @method int getTotal()
 * @method setTotal($total)
 * @method int getOrderLimits()
 * @method setOrderLimits($limits)
 * @method int getOrderNo()
 * @method setOrderNo($no)
 * @method int getType()
 * @method setType($type)
 * @method string getGroupName()
 * @method setGroupName($group)
 * @method void setState(int $state)
 * @method string getUrl()
 * @method setUrl($url)
 * @method int getShared()
 * @method setShared($shared)
 * @method getExtraData(string $string, int $int)
 */
class accountModelObj extends modelObj
{
    /** @var int */
    protected $id;

    /** @var int */
    protected $uniacid;

    /** @var int */
    protected $agent_id;

    /** @var string */
    protected $uid;

    /** @var string */
    protected $name;

    /** @var string */
    protected $title;

    /** @var string */
    protected $descr;

    /** @var string */
    protected $img;

    /** @var string */
    protected $qrcode;

    /** @var string */
    protected $clr;

    /** @var int */
    protected $count;

    /** @var int */
    protected $sccount;

    /** @var string */
    protected $scname;

    /** @var int */
    protected $total;

    /** @var int */
    protected $order_limits;

    /** @var int */
    protected $order_no;

    /** @var string */
    protected $group_name;

    /** @var int */
    protected $type;

    /** @var int */
    protected $state;

    /** @var string */
    protected $url;

    /** @var bool */
    protected $shared;

    protected $extra;

    /** @var int */
    protected $createtime;

    use ExtraDataGettersAndSetters;

    public static function getTableName($readOrWrite): string
    {
        return tb('account');
    }

    public function profile(): array
    {
        return [
            'id' => $this->getId(),
            'type' => $this->getType(),
            'banned' => $this->isBanned(),
            'clr' => $this->getClr(),
            'name' => $this->getName(),
            'uid' => $this->getUid(),
            'title' => $this->getTitle(),
            'descr' => $this->getDescription(),
            'img' => $this->isThirdPartyPlatform() ? $this->getImg() : Util::toMedia($this->getImg(), true),
            'qrcode' => Util::toMedia($this->getQrcode(), true),
        ];
    }

    public function isBanned(): bool
    {
        if ($this->state == Account::BANNED) {
            return true;
        }

        if ($this->isDouyin()) {
            return !App::isDouyinEnabled();
        }

        if ($this->isThirdPartyPlatform()) {
            static $status = null;
            if (is_null($status)) {
                $status = [
                    Account::JFB => App::isJfbEnabled(),
                    Account::MOSCALE => App::isMoscaleEnabled(),
                    Account::YUNFENBA => App::isYunfenbaEnabled(),
                    Account::AQIINFO => App::isAQiinfoEnabled(),
                    Account::ZJBAO => App::isZJBaoEnabled(),
                    Account::MEIPA => App::isMeiPaEnabled(),
                    Account::KINGFANS => App::isKingFansEnabled(),
                    Account::SNTO => App::isSNTOEnabled(),
                    Account::YFB => App::isYFBEnabled(),
                    Account::WxWORK => App::isWxWorkEnabled(),
                    Account::YOUFEN => App::isYouFenEnabled(),
                    Account::MENGMO => App::isMengMoEnabled(),
                    Account::YIDAO => App::isYiDaoEnabled(),
                    Account::WEISURE => App::isWeiSureEnabled(),
                ];
            }

            $state = $status[$this->getType()];
            if (isset($state) && !$state) {
                return true;
            }
        }

        return false;
    }

    public function getDescription(): string
    {
        return $this->descr ?: DEFAULT_ACCOUNT_DESC;
    }

    public function getMedia(): string
    {
        return $this->isVideo() ? $this->qrcode : '';
    }

    public function setMedia($url)
    {
        if ($this->isVideo()) {
            $this->setQrcode($url);
        }
    }

    public function getDuration(): int
    {
        return intval($this->settings('config.video.duration', 1));
    }

    public function setDuration($duration)
    {
        return $this->settings('config.video.duration', intval($duration));
    }

    public function getTitle(): string
    {
        return empty($this->title) ? $this->name : $this->title;
    }

    public function title(): string
    {
        return $this->getTitle();
    }

    public function name(): string
    {
        return $this->getName();
    }

    public function logQuery($cond = []): modelObjFinder
    {
        return m('account_logs')->where($cond);
    }

    public function getBonusType(): string
    {
        $commission = $this->settings('commission', []);
        if (App::isBalanceEnabled() && isset($commission['balance'])) {
            return Account::BALANCE;
        }
        if (App::isCommissionEnabled() && isset($commission['money'])) {
            return Account::COMMISSION;
        }

        return '';
    }

    public function getBalancePrice(): int
    {
        return App::isBalanceEnabled() ? $this->settings('commission.balance', 0) : 0;
    }

    public function getCommissionPrice(): int
    {
        return App::isCommissionEnabled() ? $this->settings('commission.money', 0) : 0;
    }

    public function format(): array
    {
        return Account::format($this);
    }

    public function isThirdPartyPlatform(): bool
    {
        return in_array($this->getType(), [
            Account::JFB,
            Account::MOSCALE,
            Account::YUNFENBA,
            Account::AQIINFO,
            Account::ZJBAO,
            Account::MEIPA,
            Account::KINGFANS,
            Account::SNTO,
            Account::YFB,
            Account::WxWORK,
            Account::YOUFEN,
            Account::MENGMO,
            Account::YIDAO,
            Account::WEISURE,
        ]);
    }

    public function setConfig(string $path = '', $data = []): bool
    {
        if (empty($path)) {
            return $this->set('config', $data);
        }

        return $this->updateSettings('config.'.$path, $data);
    }

    public function getConfig($path = '', $default = null)
    {
        if (empty($path)) {
            return $this->get('config', $default);
        }

        return $this->settings('config.'.$path, $default);
    }

    public function checkAnswer(userModelObj $user, array $answer = []): array
    {
        $num = 0;
        $err = null;
        $stats = [];

        $questions = $this->getQuestions($user, true);

        foreach ($questions as $question) {
            $uid = $question['id'];
            if ($question['necessary'] && empty($answer[$uid])) {
                $num = -1;
                break;
            }

            if ($question['type'] == 'choice') {
                if (!is_array($answer[$uid])) {
                    continue;
                }
                if ((array)$question['answer'] == $answer[$uid]) {
                    $stats[] = $uid;
                    $num++;
                }
            } elseif ($question['type'] == 'text') {
                $text = trim($answer[$uid]);
                if ($question['constraints'] == 'tel') {
                    if (preg_match(REGULAR_TEL, $text)) {
                        $num++;
                        $stats[] = $uid;
                        continue;
                    }

                    if ($question['necessary']) {
                        $err = '??????????????????????????????????????????';
                        $num = -1;
                        break;
                    }

                } elseif ($question['constraints'] == 'email') {
                    if (preg_match(REGULAR_EMAIL, $text)) {
                        $num++;
                        $stats[] = $uid;
                        continue;
                    }

                    if ($question['necessary']) {
                        $err = '??????????????????????????????????????????';
                        $num = -1;
                        break;
                    }
                } elseif ($question['constraints'] == 'normal') {
                    if ($text) {
                        $num++;
                        $stats[] = $uid;
                        continue;
                    }

                    if ($question['necessary']) {
                        $num = -1;
                        break;
                    }
                }
            }
        }

        $result = [
            'score' => $this->getScore(),
            'num' => $num,
            'stats' => $stats,
        ];

        if ($num < max(0, $this->getScore())) {
            $result['errno'] = State::FAIL;
            $result['message'] = $err ?? '?????????????????????????????????????????????????????????????????????';
        }

        return $result;
    }

    public function getScore()
    {
        return $this->getConfig('score', 0);
    }

    public function getQuestions(userModelObj $user = null, bool $get_answer = false): array
    {
        if (!$this->isQuestionnaire()) {
            return [];
        }

        $questions = [];
        foreach ((array)$this->getConfig('questions', []) as $index => $question) {
            if (empty($question['title'])) {
                continue;
            }
            $question['id'] = $user ? sha1($user->getOpenid().$index) : $index;
            if ($question['type'] == 'text') {
                $questions[] = $question;
                continue;
            }
            if ($question['type'] == 'choice') {
                $options = [];
                $answer = [];
                foreach ((array)$question['options'] as $j => $o) {
                    if (empty($o['text'])) {
                        continue;
                    }
                    $e = [
                        'text' => $o['text'],
                        'val' => $j,
                    ];
                    if ($o['answer']) {
                        $answer[] = $j;
                    }
                    $options[] = $e;
                }
                if (empty($options)) {
                    continue;
                }
                if ($get_answer) {
                    $question['answer'] = $answer;
                }
                $question['options'] = $options;
                $question['multi'] = count($answer) > 1;
                $questions[] = $question;
            }
        }

        return $questions;
    }

    public function isVideo(): bool
    {
        return $this->getType() == Account::VIDEO;
    }

    public function isDouyin(): bool
    {
        return $this->getType() == Account::DOUYIN;
    }

    public function isWxApp(): bool
    {
        return $this->getType() == Account::WXAPP;
    }

    public function isQuestionnaire(): bool
    {
        return $this->getType() == Account::QUESTIONNAIRE;
    }

    public function isJFB(): bool
    {
        return $this->getType() == Account::JFB;
    }

    public function isMoscale(): bool
    {
        return $this->getType() == Account::MOSCALE;
    }

    public function isYunfenba(): bool
    {
        return $this->getType() == Account::YUNFENBA;
    }

    public function isAQiinfo(): bool
    {
        return $this->getType() == Account::AQIINFO;
    }

    public function isZJBao(): bool
    {
        return $this->getType() == Account::ZJBAO;
    }

    public function isMeiPa(): bool
    {
        return $this->getType() == Account::MEIPA;
    }

    public function isKingFans(): bool
    {
        return $this->getType() == Account::KINGFANS;
    }

    public function isSNTO(): bool
    {
        return $this->getType() == Account::SNTO;
    }

    public function isYFB(): bool
    {
        return $this->getType() == Account::YFB;
    }

    public function isWxWork(): bool
    {
        return $this->getType() == Account::WxWORK;
    }

    public function isYouFen(): bool
    {
        return $this->getType() == Account::YOUFEN;
    }

    public function isMengMo(): bool
    {
        return $this->getType() == Account::MENGMO;
    }

    public function isYiDao(): bool
    {
        return $this->getType() == Account::YIDAO;
    }

    public function isWeiSure(): bool
    {
        return $this->getType() == Account::WEISURE;
    }

    public function isNormal(): bool
    {
        return $this->getType() == Account::NORMAL;
    }

    public function isAuth(): bool
    {
        return $this->getType() == Account::AUTH;
    }

    public function isTask(): bool
    {
        return $this->getType() == Account::TASK;
    }

    public function getAssignData(): array
    {
        return (array)$this->get('assigned', []);
    }

    public function setAssignData($data = []): bool
    {
        return $this->set('assigned', $data);
    }

    public function destroy(): bool
    {
        $this->remove('qrcodesData');
        $this->remove('limits');
        $this->remove('commission');
        $this->remove('config');
        $this->remove('assigned');
        $this->remove('authdata');
        $this->remove('profile');

        return parent::destroy();
    }

    /**
     * ???????????????????????????????????????????????????
     * 0 ?????????
     * 1 ???????????????
     * 2 ?????????
     */
    public function getServiceType()
    {
        return $this->settings('profile.authorizer_info.service_type_info.id', 0);
    }

    /**
     * ??????????????????????????????????????????
     * @return bool
     */
    public function isVerified(): bool
    {
        return $this->settings('profile.authorizer_info.verify_type_info.id', -1) != -1;
    }

    public function isServiceAccount(): bool
    {
        return $this->getServiceType() == Account::SERVICE_ACCOUNT;
    }

    public function isSubscriptionAccount(): bool
    {
        return $this->getServiceType() == Account::SUBSCRIPTION_ACCOUNT;
    }

    /**
     * ????????????????????????????????????????????????????????????????????????APP?????????
     * @param null $enable
     * @return bool
     */
    public function useAccountQRCode($enable = null): bool
    {
        if (isset($enable)) {
            return $this->updateSettings('misc.useAccountQRCode', $enable ? 1 : 0);
        }

        return App::useAccountQRCode() && $this->settings('misc.useAccountQRCode', 0);
    }

    public function getOpenMsg($from, $to, $redirect_url = ''): string
    {
        $config = $this->settings('config.open', []);
        if ($config['msg']) {
            $str = strval($config['msg']);
            if ($redirect_url) {
                if (stripos($str, '{url}') !== false && stripos($str, '{/url}') !== false) {
                    $arr = explode('{url}', $str, 2);
                    $text = $arr[0];
                    $arr = explode('{/url}', $arr[1], 2);
                    $text .= '<a href="'.$redirect_url.'">'.$arr[0].'</a>'.$arr[1];
                } else {
                    $text = str_replace('{url}', "<a href=\"$redirect_url\">??????</a>", $str);
                }
            } else {
                $text = $str;
            }

            return WxPlatform::createToUserTextMsg($from, $to, $text);

        } elseif ($config['news']) {
            $params = [
                'title' => strval($config['news']['title']),
                'desc' => strval($config['news']['desc']),
                'image' => Util::toMedia($config['news']['image']),
            ];
            if ($redirect_url) {
                $params['url'] = $redirect_url;
            }

            return WxPlatform::createToUserNewsMsg($from, $to, $params);
        }

        return '';
    }

    public function getFirstOrderData()
    {
        return $this->settings('misc.first_order');
    }

    public function setFirstOrderData(orderModelObj $order): bool
    {
        return $this->updateSettings('misc.first_order', [
            'id' => $order->getId(),
            'createtime' => $order->getCreatetime(),
        ]);
    }
}
