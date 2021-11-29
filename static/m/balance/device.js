const app = new Vue({
    el: '#app',
    data: {
        userInfo: null,
        imei: initData.imei,
        max: initData.max || 1,
        wechat: {
            qrcode: '',
            desc: '',
            visible: false
        },
        slides: [],
        groups: [],
        sales: [],
        free: {
            accounts: [],
            desc: 'Loading...',
            visible: false
        },
        pay: {
            packages: [],
            goods: [],
            desc: 'Loading...',
            visible: false,
            loading: false
        },
        balance: {
            goods: [],
            visible: false,
            loading: false
        },
        toast: {
            text: '',
            visible: false,
            timeout: null
        },
        video: {
            data: null,
            visible: false,
            countdown: 0,
            interval: null,
            player: null
        },
        passwd: {
            visible: false,
            data: null
        },
        retry: {
            visible: false,
            text: ''
        },
        saveUserProfile: false,
        isHidden: null,
        wechatState: null,
        timeout: null
    },
    created() {
        this.visibilitychange();
        this.judgeWechat();
        this.imei = this.imei.substring(this.imei.length - 6);
        if (typeof zovye_fn.retryOrder === 'function') {
            zovye_fn.retryOrder((res) => {
                if (res.status) {
                    this.retry.text = res.data.message;
                    this.retry.visible = true;
                }
            })
        }
        zovye_fn.getAdvs(10, 10, (data) => {
            this.sales = data;
        });
        zovye_fn.getAdvs(11, 1, (data) => {
            if (data.length > 0) {
                this.wechat.qrcode = data[0].data.image;
                this.wechat.desc = data[0].data.text;
            }
        });
        zovye_fn.getAdvs(9, 10, (data) => {
            this.groups = data;
        });
        zovye_fn.getAdvs('passwd', 1, (data) => {
            if (data.length > 0) {
                this.passwd = {
                    visible: true,
                    data: data[0].data
                };                
            }
        })
    },
    mounted() {
        this.getUserInfo();
        this.getSlideList();
        this.getAccountList();
        this.getGoodsList();
        this.getBalanceGoodsList();
    },
    methods: {
        judgeWechat(){
            let wechat = navigator.userAgent.match(/MicroMessenger\/([\d\.]+)/i);
            let judgewechat = wechat[1].split('.');
            if(judgewechat[0] > 7 || judgewechat[0] == 7 && (judgewechat[1] > 0 || judgewechat[1] == 0 && judgewechat[2] >= 12)) {
                this.wechatState = true;
            } else {
                this.wechatState = false;
            }
        },
        visibilitychange() {
            document.addEventListener('visibilitychange', () => {
                this.isHidden = document.hidden;
                if (this.isHidden === true && this.video.interval) {
                    this.video.player.pause();
                    clearInterval(this.video.interval);
                } else if (this.isHidden === false && this.video.countdown > 0) {
                    this.video.player.play();
                    this.playVideo();
                }
            });
        },
        getUserInfo() {
            zovye_fn.getUserInfo && zovye_fn.getUserInfo().then(res => {
                if(res.status) {
                    this.userInfo = res.data;
                }
            })
        },
        getSlideList() {
            zovye_fn.getAdvs(4, 10, (data) => {
                let array = [];
                data.forEach(e => {
                    e.data.images.forEach(img => {
                        let obj = {
                            image: img,
                            url: e.data.link,
                            id: e.id
                        };
                        array.push(obj);
                    })
                });
                this.slides = array;
                this.$nextTick(() => {
                    new Swiper('#adv-swiper-container', {
                        autoplay: {
                            delay: 3000,
                            disableOnInteraction: false
                        },
						pagination: {
							el: '.swiper-pagination'
						}
                    });
                });
            });
        },
        slideClick(item) {
            if (item.url) {
                window.location.href = item.url;
            }
        },
        getAccountList() {
            zovye_fn.getAccounts([], res => {
                this.free.accounts = res.data
                if (this.free.accounts.length > 0) {
                    this.free.desc = '还有' + this.free.accounts.length + '个未领取';
                    if (typeof zovye_fn.saveUserProfile === 'function') {
                        this.saveUserProfile = true;
                    }
                } else {
                    this.free.desc = '暂时无法领取';
                }
                if (this.wechatState === false && this.free.accounts.findIndex(e => e.username) !== -1) {
                    alert('当前微信版本过低，建议升级微信后再试！');
                }
                this.$nextTick(() => {
                    new Swiper('#account-swiper-container', {
                        effect: 'cards'
                    });
                });
                this.wechatState && this.free.accounts.forEach(account => {
                    if(account.username) {
                        this.$nextTick(() => {
                            var btn = document.getElementById(account.uid);
                            btn.addEventListener('launch', (e) => {
                                if(this.timeout) {
                                    clearTimeout(this.timeout);
                                    this.timeout = null;
                                }
                                this.timeout = setTimeout(() => {
                                    if(document.hidden) {
                                        zovye_fn.redirectToAccountGetPage && zovye_fn.redirectToAccountGetPage(account.uid);
                                    }
                                }, account.delay * 1000);
                            });
                            btn.addEventListener('error', function (e) {
                                console.log('fail', e.detail);
                            });
                        })
                    }
                });
            });
        },
        getGoodsList() {
            zovye_fn.getGoodsList((res) => {
                if(res.status) {
                    const data = res.data;
                    if(data.goods) {
                        this.pay.goods = data.goods.map(e => {
                            e.count = 1;
                            return e;
                        });
                    }
                    this.pay.packages = data.packages || [];
                    if(this.pay.packages.length === 0 && this.pay.goods.length === 0) {
                        this.pay.desc = '暂时无法购买';
                    } else {
                        let desc = '可购买';
                        this.pay.packages.forEach(e => {
                            desc = desc + e.title + '、';
                        });
                        this.pay.goods.forEach(e => {
                            desc = desc + e.name + '、';
                        });
                        this.pay.desc = desc.substr(0, desc.length - 1);
                    }
                }
            })
        },
        getBalanceGoodsList() {
            zovye_fn.getBalanceGoodsList(res => {
                if(res.status) {
                    this.balance.goods = res.data.map(e => {
                        e.count = 1;
                        return e;
                    })
                }
            })
        },
        freeClick() {
            if (this.free.accounts.length > 0) {
                this.free.visible = true;
            } else {
                this.showToast('暂时无法领取');
            }
        },
        payClick() {
            if (this.pay.packages.length > 0 || this.pay.goods.length > 0) {
                this.pay.visible = true;
            } else {
                this.showToast('暂时无法购买');
            }
        },
        balanceClick() {
            if (this.balance.goods.length > 0) {
                this.balance.visible = true;
            } else {
                this.showToast('暂时无法兑换');
            }
        },
        missionClick() {
            if(zovye_fn.redirectToBonusPage && typeof zovye_fn.redirectToBonusPage === 'function') {
                zovye_fn.redirectToBonusPage();
            } else {
                this.showToast('该功能未开启');
            }
        },
        minusClick(item, type) {
            if (item.num === 0) {
                this.showToast(type === 'goods' ? '商品已售罄' : '商品已兑完');
            } else {
                if (item.count > 1) {
                    item.count--;
                } else {
                    this.showToast('不能再减啦');
                }
            }
        },
        plusClick(item, type) {
            if (item.num === 0) {
                this.showToast(type === 'goods' ? '商品已售罄' : '商品已兑完');
            } else {
                if (item.count < item.num && item.count < this.max) {
                    item.count++;
                } else {
                    this.showToast('不能再加啦');
                }
            }
        },
        buyPackageClick(item) {
            if (!this.pay.loading) {
                this.pay.loading = true;
                zovye_fn.package_pay(item.id).then(() => {
                    this.pay.loading = false;
                }).catch(() => {
                    this.pay.loading = false;
                });
            }
        },
        buyClick(item) {
            if (item.num === 0) {
                this.showToast('商品已售罄');
            } else if (!this.pay.loading) {
                this.pay.loading = true;
                const data = {
                    goodsID: item.id,
                    total: item.count
                }
                zovye_fn.goods_wxpay(data).then(() => {
                    this.pay.loading = false;
                }).catch(() => {
                    this.pay.loading = false;
                });
            }
        },
        exchangeClick(item) {
            if (item.num === 0) {
                this.showToast('商品已兑完');
            } else if (!this.balance.loading) {
                this.balance.loading = true;
                zovye_fn.balancePay(item.id, item.count).then(res => {
                    console.log(res)
                    this.balance.loading = false;
                    if (res.status) {
                        res.data.redirect && window.location.replace(res.data.redirect);
                    } else {
                        this.showToast(res.data.msg);
                    }
                });
            }
        },
        showToast(text) {
            if (this.toast.visible) {
                clearTimeout(this.toast.timeout);
                this.toast.timeout = null;
            }
            this.toast.text = text;
            this.toast.visible = true;
            this.toast.timeout = setTimeout(() => {
                this.toast.visible = false;
            }, 2000);
        },
        playClick(item) {
            const that = this;
            if (!that.video.player) {
                that.video.data = item;
                that.video.countdown = item.duration;
                const options = {
                    autoplay: true,
                    sources: [{
                        type: "video/mp4",
                        src: that.video.data.media
                    }]
                };
            
                that.video.player = videojs('player', options, function onPlayerReady() {
                    this.play();
                    that.video.visible = true;
                    that.playVideo();
                });
            }
        },
        playVideo() {
            this.playRequest();
            this.video.interval = setInterval(() => {
                this.video.countdown--;
                if (this.video.countdown === 0) {
                    clearInterval(this.video.interval);
                }
            }, 1000);
        },
        playRequest() {
            zovye_fn.play(this.video.data.uid, this.video.data.duration - this.video.countdown, (res) => {
                if (res) {
                    if (!res.status) {
                        this.video.player.pause();
                        clearInterval(this.video.interval);
                        this.video.visible = false;
                        this.isHidden = true;
                        alert(res.data.msg || '播放出错！'); 
                    }
                    if (res.data && res.data.redirect) {
                        window.location.replace(res.data.redirect);
                    } else if (res.status) {
                        setTimeout(() => {
                            if (this.isHidden !== true) {
                                this.playRequest();
                            }
                        }, 1000)
                    }
                }
            });
        },
        orderClick() {
            if (zovye_fn.redirectToBonusPage) {
                zovye_fn.redirectToUserPage();
            } else {
                zovye_fn.redirectToOrderPage();
            }
        },
        feedbackClick() {
            zovye_fn.redirectToFeedBack();
        },
        groupClick(item) {
            window.location.href = item.data.url;
        },
        saleClick(item) {
            if (item.data.url) {
                window.location.href = item.data.url;
            }
        },
        onCopy() {
            this.passwd.visible = false;
        },
        onError() {
            this.passwd.visible = false;
        },
        retryClick() {
            zovye_fn.closeWindow && zovye_fn.closeWindow();
        },
        sexClick(value) {
            zovye_fn.saveUserProfile({sex : value});
            this.saveUserProfile = false;
        }
    }
});

function marquee() {
    const scrollWidth = $('#affiche').width();
    const textWidth = $('.affiche_text').width();
    let i = scrollWidth;
    setInterval(function () {
        i--;
        if (i < -textWidth) {
            i = scrollWidth;
        }
        $('.affiche_text').animate({
            'left': i + 'px'
        }, 20);
    }, 20);
}