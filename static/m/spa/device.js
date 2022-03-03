const app = new Vue({
    el: '#app',
    data: {
        imei: initData.imei,
        max: initData.max || 1,
        slides: [],
        groups: [],
        categories: [{
            title: "免费",
            se: true
        }, {
            title: "付费",
            se: false
        }],
        categoryIndex: 0,
        sales: [],
        toast: {
            title: "",
            show: false
        },
        qrcode: "",
        qrcodeDesc: "",
        showQrcode: false,
        detail: null,
        showDetail: false,
        ms: {
            title: "",
            url: ""
        },
        buyFlag: false,
        remain: null,
        video: {
            visible: false,
            data: null,
            countdown: 0,
            interval: null,
            player: null
        },
        isHidden: null,
        retryMsg: '',
        passwd: {
            visible: false,
            data: null
        },
        goods: [],
        packages: [],
        saveUserProfile: false,
        accounts: [],
        loading: false,
        timeout: null,
        wechatState: null
    },
    mounted() {
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

        this.loading = true
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
                    effect: 'coverflow',
                    grabCursor: true,
                    centeredSlides: true,
                    slidesPerView: 'auto',
                    coverflowEffect: {
                        rotate: 0,
                        stretch: 10,
                        depth: 250,
                        modifier: 1,
                        slideShadows: false
                    }
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
        })
    },
    created() {
        this.judgeWechat()
        if (typeof zovye_fn.retryOrder === 'function') {
            zovye_fn.retryOrder((res) => {
                if (res.status) {
                    this.retryMsg = res.data.message;
                }
            })
        }
        this.visibilitychange();
        this.imei = this.imei.substring(this.imei.length - 6);
        zovye_fn.getAdvs(10, 10, (data) => {
            this.sales = data;
        });
        zovye_fn.getAdvs(11, 1, (data) => {
            if (data.length > 0) {
                this.qrcode = data[0].data.image;
                this.qrcodeDesc = data[0].data.text;
            }
        });
        zovye_fn.getAdvs(9, 10, (data) => {
            this.groups = data;
        });
        zovye_fn.getAdvs(6, 1, (data) => {
            if (data.length > 0) {
                this.ms.url = data[0].data.url;
                this.ms.title = this.unescape(data[0].title);
            }
        });
        if (typeof zovye_fn.getDeviceRemain === 'function') {
            this.remain = zovye_fn.getDeviceRemain();
        }
        zovye_fn.getAdvs('passwd', 1, (data) => {
            if (data.length > 0) {
                this.passwd = {
                    visible: true,
                    data: data[0]
                };                
            }
        })
    },
    methods: {
        judgeWechat(){
            let wechat = navigator.userAgent.match(/MicroMessenger\/([\d\.]+)/i)
            let judgewechat = wechat[1].split('.')
            if(judgewechat[0] > 7 || judgewechat[0] == 7 && (judgewechat[1] > 0 || judgewechat[1] == 0 && judgewechat[2] >= 12)) {
                this.wechatState = true
            } else {
                this.wechatState = false
            }
        },
        getGoodsList() {
            zovye_fn.getGoodsList((res) => {
                this.loading = false
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
                if (this.accounts.length) {
                    this.categoryIndex = 0;
                } else if (this.goods.length || this.packages.length) {
                    this.categoryIndex = 1;
                }
                if (this.accounts.length === 1 && this.accounts[0].type === 40 && this.goods.length === 0 && this.packages.length == 0) {
                    window.location.replace(this.accounts[0].url);
                }
            })
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
        swiperClick(item) {
            if (item.url) {
                window.location.href = item.url;
            }
        },
        groupClick(item) {
            window.location.href = item.data.url;
        },
        saleClick(item) {
            if (item.data.url) {
                window.location.href = item.data.url;
            }
        },
        categoryClick(index) {
            this.categories.forEach((e, i) => {
                e.se = i === index;
            });
            this.categoryIndex = index;
        },
        goodsClick(item) {
            if (item.num === 0) {
                this.showToast('已售罄');
            } else {
                if (item.detail_img) {
                    this.detail = item;
                    this.showDetail = true;
                } else {
                    this.buyClick(item);
                }
            }
        },
        buyClick(item) {
            if (item.num === 0) {
                this.showToast('已售罄');
            } else {
                if (!this.buyFlag) {
                    this.buyFlag = true;
                    const data = {
                        goodsID: item.id,
                        total: item.count
                    }
                    zovye_fn.goods_wxpay(data).then(() => {
                        this.buyFlag = false;
                    }).catch(() => {
                        this.buyFlag = false;
                    });
                }
            }
        },
        showToast(title) {
            this.toast.title = title;
            if (!this.toast.show) {
                this.toast.show = true;
                setTimeout(() => {
                    this.toast.show = false;
                }, 2000);
            }
        },
        unescape(string) {
            return string
                .replace(string ? /&(?!#?\w+;)/g : /&/g, '&amp;')
                .replace(/&lt;/g, "<")
                .replace(/&gt;/g, ">")
                .replace(/&quot;/g, "\"")
                .replace(/&#39;/g, "\'");
        },
        msClick() {
            if (this.ms.url) {
                window.location.href = this.ms.url;
            }
        },
        orderClick() {
            zovye_fn.redirectToOrderPage();
        },
        feedbackClick() {
            zovye_fn.redirectToFeedBack();
        },
        reduceClick(item) {
            if (item.count > 1) {
                item.count--;
                item.price_formatted = '￥' + (item.price * item.count / 100).toFixed(2);
            }
        },
        increaseClick(item) {
            if (item.count < item.num && item.count < this.max) {
                item.count++;
                item.price_formatted = '￥' + (item.price * item.count / 100).toFixed(2);
                this.goods.every(g => {
                    if (g.id !== item.id) {
                        g.count = 1;
                        g.price_formatted = '￥' + (g.price / 100).toFixed(2);
                    }
                    return true;
                });
            }
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
        alertConfirmClick() {
            zovye_fn.closeWindow && zovye_fn.closeWindow();
        },
        onCopy() {
            this.passwd.visible = false;
        },
        onError() {
            this.passwd.visible = false;
        },
        parseCode(item) {
            const res = (item.desc || item.descr || "").match(/data-key="(.*?)"/);
            if (res && res[1]) {
                this.$copyText(res[1]).then(() => {
                    this.showToast('出货口令已复制');
                })
            }
        },
        buyPackageClick(package) {
            zovye_fn.package_pay(package.id);
        },
        sexClick(val) {
            zovye_fn.saveUserProfile({sex : val});
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