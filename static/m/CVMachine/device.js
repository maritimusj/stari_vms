const app = new Vue({
    el: '#app',
    data: {
        imei: initData.imei,
        max: initData.max || 1,
        accounts: initData.accounts.filter(e => e.qrcode),
        slides: [],
        groups: [],
        categories: {
            list: [{
                title: "免费",
                value: 'free'
            }, {
                title: "付费",
                value: 'pay'
            }],
            value: 'free'
        },
        sales: [],
        toast: {
            title: "",
            show: false
        },
        qrcode: "",
        qrcodeDesc: "",
        showQrcode: false,
        ms: {
            title: "",
            url: ""
        },
        buyFlag: false,
        remain: null,
        retryMsg: '',
        passwd: {
            visible: false,
            data: null
        },
        goods: [],
        packages: [],
        saveUserProfile: false,
        loading: false,
        currentItem: null,
        form: {
            data: {
                name: '',
                num: ''
            },
            visible: false
        }
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
        if (this.accounts.length > 0) {
            this.categories.list.push({
                title: "公众号",
                value: 'officialAccounts'
            })
        }
    },
    created() {
        if (typeof zovye_fn.retryOrder === 'function') {
            zovye_fn.retryOrder((res) => {
                if (res.status) {
                    this.retryMsg = res.data.message;
                }
            })
        }
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
        this.getGoodsList()
    },
    methods: {
        getGoodsList() {
            zovye_fn.getGoodsList((res) => {
                this.loading = false
                if(res.status) {
                    const data = res.data;
                    if (this.categories.value === 'free') {
                        this.goods = data || []
                    } else {
                        if(data.goods) {
                            this.goods = data.goods.map(e => {
                                e.count = 1;
                                return e;
                            });
                        } else {
                            this.goods = []
                        }
                        this.packages = data.packages || [];
                    }
                }
            }, this.categories.value)
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
        categoryClick(item) {
            this.categories.value = item.value
            if (item.value !== 'officialAccounts') {
                this.getGoodsList()
            } else {
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
            }
        },
        goodsClick(item) {
            if (item.num === 0) {
                this.showToast('已售罄');
            } else {
                if (this.categories.value === 'free') {
                    this.getClick(item)
                } else {
                    this.buyClick(item);
                }
            }
        },
        getClick(item) {
            this.currentItem = item
            this.getUserIDInfo()
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
        getUserIDInfo() {
            zovye_fn.getUserIDInfo(res => {
                if (res.status) {
                    this.getRedirectUrl()
                } else {
                    this.form.visible = true
                }
            })
        },
        getRedirectUrl() {
            zovye_fn.getRedirectUrl(this.currentItem.id, res => {
                if (res.data) {
                    window.location.href = res.data.url
                } else {
                    alert(res.data.msg)
                }
            })
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
        },
        onSave() {
            zovye_fn.saveUserIDInfo(this.form.name, this.form.num, res => {
                if (res.status) {
                    this.getRedirectUrl()
                } else {
                    alert(res.data.msg)
                }
            })
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