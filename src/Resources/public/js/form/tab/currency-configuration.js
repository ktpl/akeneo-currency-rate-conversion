'use strict';
define([
  'jquery',
  'underscore',
  'oro/translator',
  'backbone',
  'pim/router',
  'routing',
  'pim/form',
  'pim/i18n',
  'pim/user-context',
  'pim/fetcher-registry',
  'pim/security-context',
  'pim/dialog',
  'oro/messenger',
  'ktpl/currencyrateconversion/template/tab/currency-configuration',
  'pim/datagrid/state',
  'pim/initselect2',
  'jquery-ui',
], function (
  $,
  _,
  __,
  Backbone,
  Router,
  Routing,
  BaseForm,
  i18n,
  UserContext,
  FetcherRegistry,
  SecurityContext,
  Dialog,
  messenger,
  template,
  DatagridState,
  initSelect2
) {
  return BaseForm.extend({
    className: 'tabbable tabs-left',
    template: _.template(template),
    label: __('ktpl.currency_rate_conversion.form.currency_configuration.title'),
    code: 'ktpl_currency_rate_conversion_currency_configuration',
    events: {
      'change input.AknTextField.AknFieldConfiguration': 'updateModel',
      'click button.AknButton.AknButton--apply.currency_api': 'fetchCurrencyRates',
    },
    errors: {},

    /**
     * {@inheritdoc}
     */
    initialize: function (config) {
      this.config = config.config;
      this.state = new Backbone.Model();
      this.state.set('oldCurrenciesRate', []);
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
      var formData = this.getFormData();
      FetcherRegistry.getFetcher('currency')
        .fetchAll()
        .then(
          function (currencies) {
            this.$el.empty().append(
              this.template({
                currencies: currencies,
                formData: formData,
                oldCurrenciesRate: this.state.get('oldCurrenciesRate'),
                errors: this.errors,
                i18n: i18n,
                locale: UserContext.get('catalogLocale'),
                __: __,
              })
            );

            this.$('[data-toggle="tooltip"]').tooltip();
            $(".select2_currency_api").select2({
              data: this.getCurrencyApiOptions(),
              placeholder: "Select api",
              allowClear: true
            })
            this.delegateEvents();
            this.resetState();
            BaseForm.prototype.render.apply(this, arguments);
          }.bind(this)
        );

      return this;
    },

    /**
     * Update model after value change
     *
     * @param {Event} event
     */
    updateModel: function (event) {
      var data = _.extend({}, this.getFormData());
      let currencyCode = $(event.target).data('currencyCode');
      let targetName = $(event.target).attr('name');
      let fieldType = $(event.target).attr('type');
      var val = $(event.target).val();

      if (fieldType === "radio") {
        val = $(event.target).is(':checked');
      }

      if (_.isUndefined(data.currency_configuration.currencies_rate[currencyCode])) {
        data.currency_configuration.currencies_rate[currencyCode] = {};
        data.currency_configuration.currencies_rate = Object.assign({}, data.currency_configuration.currencies_rate);
      }

      if (fieldType == 'radio') {
        data.currency_configuration.base_currency = val ? currencyCode : '';
        data.currency_configuration.currencies_rate[currencyCode] = {
          currencyCode: currencyCode,
          rate: 1
        };
      } else {
        if (val == '' || isNaN(val) || val < 0) {
          messenger.notify(
            'error',
            __('ktpl.currency_rate_conversion.form.currency_configuration.error.rate')
          );

          return;
        }
        data.currency_configuration.currencies_rate[currencyCode] = {
          currencyCode: currencyCode,
          rate: val
        };
      }

      this.setData(data);
      this.render();
    },

    /**
     * Returns the options for Select2 library
     */
    getCurrencyApiOptions: function () {
      var options = [];
      var data = _.extend({}, this.getFormData());
      _.each(data.currency_configuration.supported_api, function (supportedApi, supportedApiName) {
        options.push({
          id: supportedApiName,
          text: __(supportedApi.label)
        })
      });

      return options
    },

    /**
     * Fetch currency rate from api
     *
     * @param {Event} event
     */
    fetchCurrencyRates: function (event) {
      var val = this.$('#pim_enrich_form_currency_api').val();
      let targetName = $(event.target).attr('name');
      if (!val) {
        this.errors[targetName] = 'Please select the api to import currency rates'
        this.render();
        return;
      }

      var postData = {
        currency_api: val
      };

      $.ajax({
        url: Routing.generate('ktpl_currency_rate_conversion_get_currency_rate'),
        type: 'POST',
        data: postData,
      })
        .then(response => {
          this.errors = {};
          this.state.set('oldCurrenciesRate', response.oldCurrenciesRate);
          this.setChangedCurrenciesRate(response.rates);
          this.render();
        })
        .fail(response => {
          this.state.set('oldCurrenciesRate', []);
          this.errors[targetName] = response.responseJSON.error;
          this.render();
        });
    },

    setChangedCurrenciesRate: function (currenciesRate) {
      var data = _.extend({}, this.getFormData());
      _.each(currenciesRate, function (currencyRate, currencyCode) {
        data.currency_configuration.currencies_rate[currencyCode] = {
          currencyCode: currencyCode,
          rate: currencyRate
        };
      });

      this.setData(data);
    },

    resetState: function () {
      this.state.set('oldCurrenciesRate', []);
      this.errors = {};
    },
  });
});
