define([
    "jquery",
    "jquery/ui",
    'mage/validation',
    'mage/translate',
    "Amasty_ShopbyLite/js/jquery.ui.touch-punch.min",
    'Amasty_ShopbyLite/js/chosen/chosen.jquery'
], function ($) {
    'use strict';

    $.widget('mage.amShopbyFilterAbstract', {
        filters: {},

        options: {
            isAjax: 0
        },

        getFilter: function () {
            var filter = {
                'code': this.element.attr('amshopby-filter-code'),
                'value': this.element.attr('amshopby-filter-value')
            };
            return filter;
        },

        apply: function (link, clearFilter) {
            try {
                if ($.mage.amShopbyAjax) {
                    $.mage.amShopbyAjax.prototype.response = null;
                }

                this.options.isAjax = $.mage.amShopbyAjax != undefined;
                var linkParam = clearFilter ? link : null;
                if (this.options.isAjax == true) {
                    this.prepareTriggerAjax(this.element, linkParam, clearFilter);
                } else {
                    window.location = link;
                }
            } catch(e) {
                window.location = link;
            }
        },

        prepareTriggerAjax: function(element, clearUrl, clearFilter, isSorting) {
            var forms = $('form[data-amshopby-filter]');
            if (typeof this.element !== 'undefined' && clearFilter) {
                var attributeName = this.element.closest(".filter-options-content").find('form').data('amshopby-filter');
                var excludedFormSelector = ((this.element.closest("div.sidebar").length == 0)
                        ? 'div.catalog-topnav' : 'div.sidebar') + ' form[data-amshopby-filter=' + attributeName +']';
                forms = forms.not(excludedFormSelector);
            }

            var existFields = [],
                savedFilters = [];
            forms.each(function (index, item) {
                var $item = $(item);
                var className = '';
                if ($item.closest('[class*="am-filter-items"]').length) {
                    className = $item.closest('[class*="am-filter-items"]')[0].className;
                } else if ($item.find('[class*="am-filter-items"]').length) {
                    className = $item.find('[class*="am-filter-items"]')[0].className;
                }
                var startPos = className.indexOf('am-filter-items'),
                    endPos = className.indexOf(' ', startPos + 1) == -1 ? 100 : className.indexOf(' ', startPos + 1),
                    filterClass = className.substring(startPos, endPos);

                if (filterClass && existFields[filterClass] && filterClass !== 'am-filter-items-attr_price') {
                    forms[index] = '';
                } else {
                    existFields[filterClass] = true;
                }

                if ($item.hasClass('am_saved_filter_values')) {
                    savedFilters.push(forms[index]);
                    forms[index] = '';
                }
            });

            var serializeForms = forms.serializeArray(),
                isPriceExist = false;
            _.each(serializeForms, function (index, item) {
               if (item['name'] == 'amshopby[price][]') {
                   isPriceExist = true;
                   return false;
               }
            });

            if (!isPriceExist && savedFilters) {
                savedFilters.forEach(function (element) {
                    serializeForms.push($(element).serializeArray()[0]);
                });
            }

            var data = this.normalizeData(serializeForms, isSorting, clearFilter);
            data.clearUrl = data.clearUrl ? data.clearUrl : clearUrl;
            element = element ? element : document;
            $(element).trigger('amshopby:submit_filters', {data: data, clearFilter: clearFilter, isSorting: isSorting});
            return data;
        },

        normalizeData: function(data, isSorting, clearFilter) {
            var normalizedData = [],
                ajaxOptions = $("body.page-with-filter, body.catalogsearch-result-index, body.cms-index-index").amShopbyAjax('option'),
                clearUrl;
            _.each(data, function(item) {
                if (item && item.value.trim() != '' && item.value != '-1') {
                    var isNormalizeItem = _.find(normalizedData, function (normalizeItem) {
                        return normalizeItem.name === item.name && normalizeItem.value === item.value
                            || item.name == 'amshopby[price][]' && normalizeItem.name === item.name;
                    });

                    if (!isNormalizeItem) {
                        if (item.name == 'amshopby[price][]') {
                            item.value = item.value.replace(/[ \r\n]/g, '');
                        }
                        normalizedData.push(item);
                        if (ajaxOptions.isCategorySingleSelect == '1'
                            && item.name === 'amshopby[cat][]'
                            && item.value != ajaxOptions.currentCategoryId
                            && !clearFilter
                            && !isSorting
                        ) {
                            clearUrl = $('*[data-amshopby-filter-request-var="cat"] *[value="' + item.value + '"]')
                                .parent().attr('href');
                        }
                    }
                }
            });

            normalizedData = this.groupDataByName(normalizedData);
            normalizedData.clearUrl = clearUrl;
            return normalizedData;
        },

        groupDataByName: function (formData, fn) {
            var hash = Object.create(null);
            return formData.reduce(function (result, currentValue) {
                if (!hash[currentValue['name']]) {
                    hash[currentValue['name']] = {};
                    hash[currentValue['name']]['name'] = currentValue['name'];
                    result.push(hash[currentValue['name']]);
                }

                if (hash[currentValue['name']].value) {
                    hash[currentValue['name']].value += ',' + currentValue.value;
                } else {
                    hash[currentValue['name']].value = currentValue.value;
                }

                return result;
            }, []);
        },

        getSignsCount: function (step, isPrice) {
            if (step < 1 && step > 0) {
                return step.toString().length - step.toString().indexOf(".") - 1;
            }

            return 0;
        },
        getFloatNumber: function (size) {
            if (!size) {
                size = 2;
            }

            return 1 / parseInt(this.buildNumber(size));
        },
        buildNumber: function (size) {
            var str = "1";
            for (var i = 1; i <= size; i++) {
                str += "0";
            }

            return str;
        },
        getFixed: function (value, isPrice) {
            var fixed = 2;
            if (value) {
                fixed = this.getSignsCount(this.options.step, isPrice);
            }

            return fixed;
        },
        isPrice: function () {
            return (typeof this.options.code != 'undefined' && this.options.code == 'price');
        },
        renderShowButton: function (event, element) {
            if ($.mage.amShopbyApplyFilters) {
                $.mage.amShopbyApplyFilters.prototype.renderShowButton(event, element);
            }
        }
    });

    $.widget('mage.amShopbyFilterItemDefault', $.mage.amShopbyFilterAbstract, {
        options: {},
        _create: function () {
            var self = this;
            $(function () {
                var link = self.element,
                    parent = link.parents('.item'),
                    checkbox = link.find('input[type=checkbox], input[type=radio]');

                if (link.find('[name="amshopby[cat][]"]').length && parent) {
                    parent = $(null);//get only current category item
                }

                var params = {
                    parent: parent,
                    checkbox: checkbox,
                    link: link
                };

                checkbox.bind('click', params, function (e) {
                    var checkbox = $(this),
                        link = e.data.link,
                        href = link.prop('href');

                    setTimeout(function () {
                        checkbox.prop('checked', !checkbox.prop('checked'));
                        self.triggerSync(checkbox, !checkbox.prop('checked'));
                        if (self.isFinderAndCategory(checkbox[0])) {
                            location.href = href;
                            return;
                        }
                        $.mage.amShopbyFilterAbstract.prototype.renderShowButton(e, link);
                        self.apply(href);
                    }, 10);
                    e.stopPropagation();
                    e.preventDefault();
                });

                link.bind('click', params, function (e) {
                    var element = e.data.checkbox,
                        href = e.data.link.prop('href');
                    element.prop('checked', !element.prop('checked'));
                    self.triggerSync(element, !element.prop('checked'));
                    if (self.isFinderAndCategory(element[0])) {
                        location.href = href;
                        return;
                    }
                    $.mage.amShopbyFilterAbstract.prototype.renderShowButton(e, element);
                    self.apply(href);

                    e.stopPropagation();
                    e.preventDefault();
                });

                parent.bind('click', params, function (e) {
                    var element = e.data.checkbox;
                    var link = e.data.link;
                    element.prop('checked', !element.prop('checked'));
                    self.triggerSync(element, !element.prop('checked'));
                    $.mage.amShopbyFilterAbstract.prototype.renderShowButton(e, element);
                    self.apply(link.prop('href'));

                    e.stopPropagation();
                    e.preventDefault();
                    return false;
                });

                checkbox.on('change', function (e) {
                    self.markAsSelected($(this));
                });

                checkbox.on('amshopby:sync_change', function (e) {
                    self.markAsSelected($(this));
                });

                self.markAsSelected(checkbox);
            })
        },
        triggerSync: function (element, clearFilter) {
            element.trigger('change');
            element.trigger('sync', [clearFilter]);
        },
        isFinderAndCategory: function (element) {
            return location.href.indexOf('find=') !== -1
                && element.type == 'radio'
                && element.name == 'amshopby[cat][]';
        },
        markAsSelected: function (checkbox) {
            checkbox.closest('form').find('a').each(function () {
                if (!$(this).find('input').prop("checked")) {
                    $(this).removeClass('am_shopby_link_selected');
                } else {
                    $(this).addClass('am_shopby_link_selected');
                }
            });
        }
    });

    $.widget('mage.amShopbyFilterSlider', $.mage.amShopbyFilterAbstract, {
        options: {},
        slider: null,
        value: null,
        display: null,
        _create: function () {
            $(function () {
                this.value = this.element.find('[amshopby-slider-id="value"]');
                this.slider = this.element.find('[amshopby-slider-id="slider"]');
                this.display = this.element.find('[amshopby-slider-id="display"]');
                var fromLabel = this.options.from && this.options.from >= this.options.min
                    ? this.options.from
                    : this.options.min;
                var toLabel = this.options.to && this.options.to <= this.options.max
                    ? this.options.to
                    : this.options.max;

                this.slider.slider({
                    step: this.options.step,
                    range: true,
                    min: this.options.min,
                    max: this.options.max,
                    values: [fromLabel, toLabel],
                    slide: this.onSlide.bind(this),
                    change: this.onChange.bind(this)
                });

                if (this.options.to) {
                    this.value.val(fromLabel + '-' + toLabel);
                } else {
                    this.value.trigger('change');
                }

                this.renderLabel(fromLabel, toLabel);
            }.bind(this));
        },

        onChange: function (event, ui) {
            if (this.slider.skipOnChange !== true) {
                this.setValue(
                    ui.values[0],
                    ui.values[1],
                    true,
                    jQuery(ui.handle).closest('[data-am-js="slider-container"]').attr('rate')
                );
            }

            return true;
        },

        onSlide: function (event, ui) {
            var from = ui.values[0];
            var to = ui.values[1];

            this.setValue(from, to, false);
            this.renderLabel(from, to);
            return true;
        },

        onSyncChange: function (event, values) {
            var value = values[0].split('-');
            if (value.length === 2) {
                var fixed = 2,
                    from = (parseFloat(value[0])).toFixed(fixed),
                    to = parseFloat(value[1]).toFixed(fixed);

                this.slider.skipOnChange = true;

                this.slider.slider('values', [from, to]);
                this.setValueWtihoutChange(from, to);
                this.slider.skipOnChange = false;
            }
        },
        setValue: function (from, to, apply, rate) {
            var fixed = 2;
            from = (parseFloat(from)).toFixed(fixed);
            to = (parseFloat(to)).toFixed(fixed);

            var newVal = from + '-' + to,
                changed = this.value.val() != newVal;

            this.value.val(newVal);
            if (changed) {
                this.value.trigger('change');
                this.value.trigger('sync');
            }

            if (apply !== false) {
                newVal = from + '-' + to;
                this.value.val(newVal);
                var linkHref = this.options.url.replace('amshopby_slider_from', from).replace('amshopby_slider_to', to);
                this.apply(linkHref);
            }
        },
        setValueWtihoutChange: function(from, to) {
            var fixed = this.getSignsCount(this.options.step, 0);
            var newVal = parseFloat(from) + '-' + parseFloat(to);
            this.value.val(newVal);
        },
        getLabel: function (from, to) {
            return this.options.template.replace('{from}', from.toString()).replace('{to}', to.toString());
        },
        renderLabel: function (from, to) {
            var fixed = this.getSignsCount(this.options.step, 0);
            from = (parseFloat(from)).toFixed(fixed);
            to = (parseFloat(to)).toFixed(fixed);
            this.display.html(this.getLabel(from, to));
        }
    });

    $.widget('mage.amShopbyFilterContainer', {
        options: {},

        _create: function () {
            var self = this;
            $(function () {
                var $element = $(self.element[0]);
                var links = $element.find('[data-am-js="shopby-item"]');
                var allClear = $element.siblings('.filter-actions');
                var filters = [];
                if (links.length) {
                    $(links).each(function (index, value) {
                        var filter = {
                            attribute: $(value).attr("data-container"),
                            value: self.escapeHtml($(value).attr("data-value"))
                        };
                        filters.push(filter);

                        $(value).find('a').on("click", function (e) {
                            $(this).parent().addClass('am-item-removed');
                            $.mage.amShopbyFilterAbstract.prototype.renderShowButton(e, this);
                            self.apply(filter);
                            if (e) {
                                e.stopPropagation();
                                e.preventDefault();
                            }
                        });
                        if (filters.length) {
                            $.each(filters, function (index, filter) {
                                self.checkInForm(filter);
                            });
                        }
                    });
                }
            })
        },

        escapeHtml: function (text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };

            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        },

        apply: function (filter) {
            var link = $('li[data-container="' + filter.attribute + '"][data-value="' + filter.value + '"] a.remove').attr('href');

            try {
                var self = this;

                var value = filter.value;
                if (filter.attribute == 'price') {
                    var values = filter.value.split('-'),
                        fromValue = values[0] ? parseFloat(values[0]).toFixed() : '',
                        toValue = values[1] ?  parseFloat(values[1]).toFixed() : '',
                        attrSelector = '[data-amshopby-filter="attr_' + filter.attribute + '"]';

                    value = fromValue + '-' + toValue;
                }

                self.setDefault(filter.attribute, value);

                $(attrSelector).trigger('change');
                $(attrSelector).trigger('sync', [true]);

                if ($.mage.amShopbyAjax != undefined) {
                    $.mage.amShopbyFilterAbstract.prototype.prepareTriggerAjax(null, null, true);
                } else {
                    window.location = link;
                }
            } catch(e) {
                window.location = link;
            }
        },

        clearBlock: function () {
            if (!$('[data-am-js="shopby-container"]').find("li").length) {
                $('[data-am-js="shopby-container"]').remove();
                $(".filter-actions").remove();
            }
        },

        setDefault: function (name, value) {
            var valueSelector = '[name="amshopby[' + name + '][]"]',
                filters = $(valueSelector),
                self = this;

            filters.each(function (index, filter) {
                var type = $(filter).prop("tagName");

                switch (type) {
                    case 'SELECT' :
                        if (name == 'price') {
                            $(filter).find('option').each(function (index, element) {
                                if (self.toValidView(element.value.split('-')) == this) {
                                    element.selected = false;
                                }
                            }.bind(value));
                        } else {
                            $(filter).find('[value="' + value + '"]').removeAttr('selected', 'selected');
                        }
                        break;
                    case 'INPUT' :
                        var selected = '';
                        if ($(filter).attr("type") != 'text' && $(filter).attr("type") != 'hidden') {
                            selected = $(valueSelector + '[value="' + value + '"]');
                            selected.removeAttr("checked");
                            selected.siblings('.swatch-option.selected').removeClass('selected');
                        } else if (($(filter).attr("type") == 'hidden' && self.isEquals(name, filter.value, value))
                            || name =='price'
                        ) {
                            filter.value = "";
                        }
                        break;
                }
            });
        },

        isEquals: function (name, filterValue, value) {
            var values = value.split('-'),
                filterValues = filterValue.split('-');
            if (values.length > 1) {
                filterValue = this.toValidView(filterValues);
                value = this.toValidView(values);
            }

            return filterValue == value;
        },

        toValidView: function (values) {
            values[0] = values[0] ? parseFloat(values[0]).toFixed() : values[0];
            values[1] = values[1] ? parseFloat(values[1]).toFixed() : values[1];

            return values[0] + '-' + values[1];
        },

        sliderDefault: function (name) {
            var valueSelector = '[name="amshopby[' + name + '][]"]',
                slider = $(valueSelector).siblings('[amshopby-slider-id="slider"]');
            if (slider.length) {
                var $parent = $(valueSelector).parent();
                $(slider[0]).slider("values", [$parent.attr('data-min'), $parent.attr('data-max')]);
                $(slider).siblings('[data-am-js="slider-display"]').text($parent.attr('data-min') + ' - ' + $parent.attr('data-max'));
            }
        },

        fromToDefault: function (name) {
            var range = $('[name="amshopby[' + name + '][]"]').siblings('.range');
            if (range.length) {
                var from = range.find('[amshopby-fromto-id="from"]'),
                    to = range.find('[amshopby-fromto-id="to"]'),
                    digits = $(from).attr('validate-digits-range'),
                    regexp = /\[([\d\.]+)-([\d\.]+)\]/g,
                    ranges = regexp.exec(digits);

                $(from).val(ranges[1]);
                $(to).val(ranges[2]);
            }
        },

        checkInForm: function (filter) {
            var name = filter.attribute,
                value = filter.value,
                notExistValue = true;
            $('[name="amshopby[' + name + '][]"]').each(function (index, item) {
                if (!item.value || item.value == value) {
                    notExistValue = false;
                }
            });
            if (notExistValue) {
                $('#layered-filter-block').append('<form class="am_saved_filter_values" data-amshopby-filter="attr_' + name + '"><input value="' + value + '" type="hidden" name="amshopby[' + name + '][]"></form>')
            }
        }
    });
});
