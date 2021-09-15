define([
    'jquery',
    'productListToolbarForm',
    'jquery/ui',
    'amShopbyFilterAbstract'
], function ($) {
    'use strict';

    $.widget('mage.amShopbyAjax', {
        prevCall: false,
        $shopbyOverlay: null,
        cached: [],
        blockToolbarProcessing: false,
        response: null,
        cacheKey: null,
        isCategorySingleSelect: 0,
        selectors: {
            swatchesTooltip: '.swatch-option-tooltip',
            sidebar: '.sidebar.sidebar-main',
            filtersBlock: '.block.filter',
            shopByProducts: '#amasty-shopby-product-list',
            productsWrapper: '.products.wrapper',
            pageTitle: '#page-title-heading',
            pageTitleWrapper: '.page-title-wrapper',
            breadcrumbs: '.breadcrumbs',
            categoryView: '.category-view',
            jsInit: '[data-am-js="js-init"]',
            pagination: '.toolbar .pages a',
            overlay: '#amasty-shopby-overlay'
        },
        options: {
            scrollUp: false
        },

        _create: function () {
            this.initWidget();
            this._interceptBackAction();
        },

        initWidget: function () {
            var self = this,
                swatchesTooltip = $(self.selectors.swatchesTooltip);

            $(document).on('amshopby:submit_filters', function (event, eventData) {
                var data = eventData.data,
                    clearUrl = self.options.clearUrl,
                    isSorting = eventData.isSorting,
                    pushState = !self.options.submitByClick;

                if (typeof data.clearUrl !== 'undefined') {
                    clearUrl = data.clearUrl;
                    delete data.clearUrl;
                }
                if (self.prevCall) {
                    self.prevCall.abort();
                }

                var dataAndUrl = data.slice(0);
                dataAndUrl.push(clearUrl ? clearUrl : self.options.clearUrl);

                var cacheKey = JSON.stringify(dataAndUrl);
                $.mage.amShopbyAjax.prototype.cacheKey = cacheKey;
                if (self.cached[cacheKey]) {
                    window.history.pushState({url: self.cached[cacheKey].url}, '', self.cached[cacheKey].url);
                    self.reloadHtml(self.cached[cacheKey]);
                    self.initAjax();
                } else {
                    self.prevCall = self.callAjax(clearUrl, data, pushState, cacheKey, isSorting);
                }
            });

            $(document).on('touchstart touchmove scroll', function () {
                swatchesTooltip.hide();
            });

            self.initAjax();
        },

        initAjax: function () {
            var productListContainer = $(this.selectors.shopByProducts + ' ' + this.selectors.productsWrapper);
            this.$shopbyOverlay = $(this.selectors.overlay);
            if (!this.$shopbyOverlay.length) {
                productListContainer
                    .append(
                        "<div id='" +
                        this.selectors.overlay.split('#').join('') +
                        "'><div class='loader'></div></div>"
                    );
                this.$shopbyOverlay = $(this.selectors.overlay);
            }

            this._initAjaxToolbar();
            this._initAjaxPagination();
        },

        callAjax: function (clearUrl, data, cacheKey) {
            var self = this;
            this.$shopbyOverlay.show();

            data.every(function (item, key) {
                if (item.name.indexOf('[cat]') != -1) {
                    if (item.value == self.options.currentCategoryId) {
                        data.splice(key, 1);
                    } else {
                        item.value.split(',').filter(function(element) {
                            return element != self.options.currentCategoryId
                        }).join(',');
                    }

                    return false;
                }
            });
            data.push({name: 'shopbyAjax', value: 1});

            if (!clearUrl) {
                clearUrl = self.options.clearUrl;
            }

            self.clearUrl = clearUrl;

            return $.ajax({
                url: clearUrl,
                data: data,
                cache: true,

                success: function (response) {
                    try {
                        response = $.parseJSON(response);

                        if (response.isDisplayModePage) {
                            throw new Error();
                        }

                        if (cacheKey) {
                            self.cached[cacheKey] = response;
                        }

                        if (response.newClearUrl
                            && (response.newClearUrl.indexOf('?p=') == -1 && response.newClearUrl.indexOf('&p=') == -1)) {
                            self.options.clearUrl = response.newClearUrl;
                        }

                        window.history.pushState({url: response.url}, '', response.url);
                        self.reloadHtml(response);
                    } catch (e) {
                        var url = self.clearUrl ? self.clearUrl : self.options.clearUrl;
                        window.location = (this.url.indexOf('shopbyAjax') == -1) ? this.url : url;
                    }
                },
                error: function (response) {
                    try {
                        if (response.getAllResponseHeaders() != '') {
                            self.options.clearUrl ? window.location = self.options.clearUrl : location.reload();
                        }
                    } catch (e) {
                        window.location = (this.url.indexOf('shopbyAjax') == -1) ? this.url : self.options.clearUrl;
                    }
                }
            });
        },

        reloadHtml: function (data) {
            var sidebarBlock = $(this.selectors.sidebar).first(),
                productsBlock = $(this.selectors.shopByProducts),
                pageTitle = $(this.selectors.pageTitle),
                swatchesTooltip = $(this.selectors.swatchesTooltip),
                categoryViewBlock = $(this.selectors.categoryView),
                jsInit = $(this.selectors.jsInit),
                resolveFilterBlock = function () {
                    var filtersBlock = sidebarBlock.find(this.selectors.filtersBlock).first();
                    if (!filtersBlock.length) {
                        if (sidebarBlock.length) {
                            sidebarBlock.prepend(
                                '<div class=\'' +
                                this.selectors.filtersBlock.split('.').join(' ') +
                                '\'></div>'
                            );
                        }
                        filtersBlock = $(this.selectors.filtersBlock).first();
                    }
                    return filtersBlock;
                }.bind(this);

            this.options.currentCategoryId = data.currentCategoryId
                ? data.currentCategoryId
                : this.options.currentCategoryId;


            resolveFilterBlock().replaceWith(data.navigation);
            resolveFilterBlock().trigger('contentUpdated');

            if (data.categoryProducts) {
                productsBlock.replaceWith(data.categoryProducts);
                productsBlock = $(this.selectors.shopByProducts);
                productsBlock.trigger('contentUpdated');
            }

            pageTitle.closest(this.selectors.pageTitleWrapper).replaceWith(data.h1);
            pageTitle.trigger('contentUpdated');

            $(this.selectors.breadcrumbs).replaceWith(data.breadcrumbs);
            $(this.selectors.breadcrumbs).trigger('contentUpdated');

            $('title').html(data.title);

            if (data.categoryData != '') {
                if (!categoryViewBlock.length) {
                    categoryViewBlock = $(
                        '<div class="' +
                        this.selectors.categoryView.split('.').join(' ') +
                        '"></div>'
                    ).insertAfter('.page.messages');
                }
                categoryViewBlock.replaceWith(data.categoryData);
            }

            swatchesTooltip.hide();

            if (this.$shopbyOverlay) {
                this.$shopbyOverlay.hide();
            }

            if (this.options.scrollUp && productsBlock.length) {
                $(document).scrollTop(productsBlock.offset().top);
            }

            jsInit.first().replaceWith(data.js_init);
            jsInit = $(this.selectors.jsInit);
            jsInit.first().trigger('contentUpdated');

            this.afterChangeContentExternal();

            $(document).trigger('amscroll_refresh');

            this.initAjax();
        },

        afterChangeContentExternal: function () {
            //compatibility with Amasty Scroll extension
            $(document).trigger('amscroll_refresh');

            //porto theme compatibility
            var lazyImg = $("img.porto-lazyload:not(.porto-lazyload-loaded)");
            if (lazyImg.length && typeof $.fn.lazyload == 'function') {
                lazyImg.lazyload({effect:"fadeIn"});
            }
        },

        _initAjaxToolbar: function () {
            if ($.mage.productListToolbarForm) {
                var self = this;
                //change page limit
                $.mage.productListToolbarForm.prototype.changeUrl = function (paramName, paramValue, defaultValue) {
                    // Workaround to prevent double method call
                    if (self.blockToolbarProcessing) {
                        return;
                    }
                    self.blockToolbarProcessing = true;
                    setTimeout(function () {
                        self.blockToolbarProcessing = false;
                    }, 300);

                    var decode = window.decodeURIComponent,
                        urlPaths = this.options.url.split('?'),
                        urlParams = urlPaths[1] ? urlPaths[1].split('&') : [],
                        paramData = {};

                    for (var i = 0; i < urlParams.length; i++) {
                        var parameters = urlParams[i].split('=');
                        paramData[decode(parameters[0])] = parameters[1] !== undefined
                            ? decode(parameters[1].replace(/\+/g, '%20'))
                            : '';
                    }
                    paramData[paramName] = paramValue;
                    if (paramValue == defaultValue) {
                        delete paramData[paramName];
                    }
                    self.options.clearUrl = self.getNewClearUrl(paramName, paramData[paramName] ? paramData[paramName] : '');

                    //add ajax call
                    $.mage.amShopbyFilterAbstract.prototype.prepareTriggerAjax(null, null, null, true);
                };
            }
        },

        //change page number
        _initAjaxPagination: function () {
            var self = this;
            $(this.selectors.pagination).unbind('click').bind('click', function (e) {
                var newUrl = $(this).prop('href'),
                    updatedUrl = null,
                    urlPaths = newUrl.split('?'),
                    urlParams = urlPaths[1].split('&');

                for (var i = 0; i < urlParams.length; i++) {
                    if (urlParams[i].indexOf("p=") === 0) {
                        var pageParam = urlParams[i].split('=');
                        updatedUrl = self.getNewClearUrl(pageParam[0], pageParam[1] > 1 ? pageParam[1] : '');
                        break;
                    }
                }

                if (!updatedUrl) {
                    updatedUrl = this.href;
                }
                updatedUrl = updatedUrl.replace('amp;', '');
                $.mage.amShopbyFilterAbstract.prototype.prepareTriggerAjax(document, updatedUrl, false, true);
                $(document).scrollTop($(self.selectors.shopByProducts).offset().top);

                e.stopPropagation();
                e.preventDefault();
            });
        },

        //Update url after change page size or current page.
        getNewClearUrl: function (param, value) {
            var urlPaths = this.options.clearUrl.replace(/&amp;/g, '&').split('?'),
                baseUrl = urlPaths[0],
                urlParams = urlPaths[1] ? urlPaths[1].split('&') : [param + '=' + value],
                replaced = false,
                paramData = {};

            for (var i = 0; i < urlParams.length; i++) {
                var parameters = urlParams[i].split('=');
                paramData[parameters[0]] = parameters[1];
                if (parameters[0] == param) {
                    if (value != '') {
                        paramData[parameters[0]] = value;
                    } else {
                        delete paramData[parameters[0]];
                    }
                    replaced = true;
                }
            }
            if (!replaced && value != '') {
                paramData[param] = value;
            }

            paramData = $.param(paramData);

            return window.decodeURIComponent(baseUrl + (paramData.length ? '?' + paramData : ''));
        },

        _interceptBackAction: function () {
            if (typeof window.history.replaceState === 'function') {
                var self = this;

                window.history.replaceState(
                    {
                        url: document.URL
                    },
                    document.title
                );
                setTimeout(function () {
                    /*
                     Timeout is a workaround for iPhone
                     Reproduce scenario is following:
                     1. Open category
                     2. Use pagination
                     3. Click on product
                     4. Press "Back"
                     Result: Ajax loads the same content right after regular page load
                     */
                    window.onpopstate = function (e) {
                        if (e.state) {
                            self.callAjax(e.state.url, []);
                            self.$shopbyOverlay.show();
                        }
                    };
                }, 0);
            }
        }
    });
});
