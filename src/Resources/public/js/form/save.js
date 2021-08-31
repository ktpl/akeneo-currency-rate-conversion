'use strict';

define([
        'underscore',
        'jquery',
        'routing',
        'pim/form/common/save',
        'pim/template/form/save'
    ],
    function(
        _,
        $,
        Routing,
        SaveForm,
        template
    ) {
        return SaveForm.extend({
            config: [],
            template: _.template(template),
            currentKey: 'current_form_tab',
            events: {
                'click .save': 'save'
            },

            initialize: function (config) {
                this.config = config.config;
            },

            /**
             * {@inheritdoc}
             */
            render: function () {
                this.$el.html(this.template({
                    label: _.__('pim_common.save')
                }));
            },

            /**
             * {@inheritdoc}
             */
            save: function () {
                this.getRoot().trigger('pim_enrich:form:entity:pre_save', this.getFormData());
                this.showLoadingMask();

                var data = this.stringify(this.getFormData());
                $.ajax({
                    method: 'POST',
                    url: this.getSaveUrl(),
                    contentType: 'application/json',
                    data: data
                })
                .then(this.postSave.bind(this))
                .fail(this.fail.bind(this))
                .always(this.hideLoadingMask.bind(this));
            },

            stringify: function(formData) {
                if('undefined' != typeof(formData['currency_configuration']) && formData['currency_configuration'] instanceof Array) {
                    formData['currency_configuration'] = $.extend({}, formData['currency_configuration']);
                }

                return JSON.stringify(formData);                
            },

            /**
             * {@inheritdoc}
             */
            getSaveUrl: function () {
                if(this.config && this.config.postUrl) {
                    var url = this.config.postUrl;
                    return Routing.generate(url);
                } else {
                    var url = __moduleConfig.route;
                    return Routing.generate(url);
                }
            },

            /**
             * {@inheritdoc}
             */
            postSave: function (data) {
                this.setData(data);
                this.getRoot().trigger('pim_enrich:form:entity:post_fetch', data);

                SaveForm.prototype.postSave.apply(this, arguments);
            }     
        });
    }
);
