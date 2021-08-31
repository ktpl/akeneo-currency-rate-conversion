'use strict';
define([
    'jquery',
    'underscore',
    'oro/translator',
    'pim/router',
    'pim/form',
    'pim/i18n',
    'pim/user-context',
    'pim/fetcher-registry',
    'pim/security-context',
    'pim/dialog',
    'oro/messenger',
    'ktpl/currencyrateconversion/template/tab/currency-api-setup',
    'pim/datagrid/state',
    'jquery-ui',
], function (
    $,
    _,
    __,
    Router,
    BaseForm,
    i18n,
    UserContext,
    FetcherRegistry,
    SecurityContext,
    Dialog,
    messenger,
    template,
    DatagridState
) {
    return BaseForm.extend({
        className: 'tabbable tabs-left',
        template: _.template(template),
        label: __('ktpl.currency_rate_conversion.form.currency_api_setup.title'),
        code: 'ktpl_currency_rate_conversion_currency_api_setup',
        events: {
            'change input.AknTextField.AknFieldConfiguration, select': 'updateModel',
        },

        /**
         * {@inheritdoc}
         */
        initialize: function (config) {
            this.config = config.config;
            BaseForm.prototype.initialize.apply(this, arguments);
        },

        /**
         * {@inheritdoc}
         */
        configure: function () {
            this.trigger('tab:register', {
                code: this.code,
                label: this.label,
            });

            return BaseForm.prototype.configure.apply(this, arguments);
        },

        /**
         * {@inheritdoc}
         */
        render: function () {
            this.$el.empty().append(
                this.template({
                    formData: this.getFormData(),
                    i18n: i18n,
                    locale: UserContext.get('catalogLocale'),
                    __: __,
                })
            );

            this.$('[data-toggle="tooltip"]').tooltip();
            $(".select2").select2();
            this.delegateEvents();
            BaseForm.prototype.render.apply(this, arguments);

            return this;
        },

        /**
         * Update model after value change
         *
         * @param {Event} event
         */
        updateModel: function (event) {
            var data = _.extend({}, this.getFormData());
            let apiName = $(event.target).data('apiName');
            let targetName = $(event.target).attr('name');
            var val = val = $(event.target).val();
            if (_.isUndefined(data.currency_configuration.supported_api_key[apiName])) {
                data.currency_configuration.supported_api_key[apiName] = {};
                data.currency_configuration.supported_api_key = Object.assign({}, data.currency_configuration.supported_api_key);
            }

            data.currency_configuration.supported_api_key[apiName][targetName] = val

            this.setData(data);
        },
    });
});
