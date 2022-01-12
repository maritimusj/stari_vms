const app = new Vue({
    el: '#app',
    data: {
        imei: initData.imei,
        max: initData.max || 1,
        wechat: {
            qrcode: '',
            desc: '',
            visible: false
        },
        slides: [],
        tabbar: initData.tabbar,
        group: [],
        accounts: [],
        packages: [],
        goods: [],
        sales: [],
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
        timeout: null,
        loading: false
    },
    mounted() {
        this.getSlideList();
        this.getAccountList();
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
            this.group = data;
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
                        array.push(obj)
                    })
                });
                this.slides = array;
                Vue.nextTick(() => {
                    new Swiper('#adv-swiper-container', {
                        autoplay: {
                            delay: 3000,
                            disableOnInteraction: false
                        }
                    });
                })
            });
        },
        getAccountList() {
            this.loading = true;
            zovye_fn.getAccounts([], res => {
                if (res && res.status && res.data) {
                    res.data.forEach(e => {
                        if (e.redirect_url) {
                            window.location.replace(e.redirect_url);
                        } else {
                            this.accounts.push(e);
                        }
                    });
                }
                if (typeof zovye_fn.saveUserProfile === 'function' && this.accounts.length > 0) {
                    this.saveUserProfile = true;
                }
                if (this.wechatState === false && this.accounts.findIndex(e => e.username) !== -1) {
                    alert('当前微信版本过低，建议升级微信后再试！')
                }
                Vue.nextTick(() => {
                    new Swiper('#account-swiper-container', {
                        effect: 'cards'
                    });
                })
                this.getGoodsList()
                this.wechatState && this.accounts.forEach(account => {
                    if(account.username) {
                        Vue.nextTick(() => {
                            var btn = document.getElementById(account.uid);
                            btn.addEventListener('launch', (e) => {
                                if(this.timeout) {
                                    clearTimeout(this.timeout)
                                    this.timeout = null
                                }
                                this.timeout = setTimeout(() => {
                                    if(document.hidden) {
                                        zovye_fn.redirectToAccountGetPage && zovye_fn.redirectToAccountGetPage(account.uid)
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
                this.loading = false;
                if(res.status) {
                    const data = res.data;
                    if(data.goods) {
                        this.goods = data.goods.map(e => {
                            e.count = 1;
                            return e;
                        });
                    }
                    this.packages = data.packages || [];
                }
                if (this.accounts.length === 0 && (this.goods.length > 0 || this.packages.length > 0)) {
                    this.tabbar.currentValue = 'buy';
                }
            })
        },
        slideClick(item) {
            if (item.url) {
                window.location.href = item.url;
            }
        },
        feedbackClick() {
            zovye_fn.redirectToFeedBack();
        },
        groupClick(item) {
            window.location.href = item.data.url;
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
                        window.location.replace(res.data.redirect)
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
        minusClick(item) {
            if (item.num === 0) {
                this.showToast('商品已售罄');
            } else {
                if (item.count > 1) {
                    item.count--;
                } else {
                    this.showToast('不能再减啦')
                }
            }
        },
        plusClick(item) {
            if (item.num === 0) {
                this.showToast('商品已售罄');
            } else {
                if (item.count < item.num && item.count < this.max) {
                    item.count++;
                } else {
                    this.showToast('不能再加啦')
                }
            }
        },
        tabClick(index) {
            this.tabbar.currentValue = this.tabbar.list[index].value;
            if (index === 2) {
                zovye_fn.redirectToOrderPage();
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
                this.toast.visible = false
            }, 2000)
        },
        buyPackageClick(item) {
            zovye_fn.package_pay(item.id);
        },
        buyClick(item) {
            if (item.num === 0) {
                this.showToast('商品已售罄');
            } else if (!this.loading) {
                this.loading = true;
                const data = {
                    goodsID: item.id,
                    total: item.count
                }
                zovye_fn.goods_wxpay(data).then(() => {
                    this.loading = false;
                }).catch(() => {
                    this.loading = false;
                });
            }
        },
        onCopy() {
            this.passwd.visible = false;
        },
        onError() {
            this.passwd.visible = false;
        },
        parseCode(item) {
            const res = (item.desc || item.descr || "").match(/data-key=\"(.*)\"/);
            if (res && res[1]) {
                this.$copyText(res[1]).then(() => {
                    this.showToast('出货口令已复制');
                })
            }
        },
        retryClick() {
            zovye_fn.closeWindow && zovye_fn.closeWindow();
        },
        saleClick(item) {
            if (item.data.url) {
                window.location.href = item.data.url;
            }
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