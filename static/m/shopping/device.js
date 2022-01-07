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
        accounts: initData.accounts,
        goods: initData.goods,
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
        online: true
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
    },
    created() {
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
        if (this.accounts.length) {
            this.categoryIndex = 0;
        } else if (this.goods.length) {
            this.categoryIndex = 1;
        }
        if (typeof zovye_fn.getDeviceRemain === 'function') {
            this.remain = zovye_fn.getDeviceRemain();
        }
    },
    methods: {
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
            if (!this.toast.show) {
                this.toast.title = title;
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
        parseCode(item) {
            const res = (item.desc || item.descr || "").match(/data-key=\"(.*)\"/);
            if (res && res[1]) {
                this.$copyText(res[1]).then(() => {
                    this.showToast('出货口令已复制');
                })
            }
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