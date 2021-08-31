'use strict';
define(
    [
        'jquery',
        'underscore',
        'oro/translator',
        'pim/controller/front',
        'pim/form-builder',
        'pim/page-title',
        'routing'
    ],
    function ($, _, __, BaseController, FormBuilder, PageTitle, Routing) {
        return BaseController.extend({
            
            /**
             * {@inheritdoc}
             */
            renderForm: function () {
                return $.when(
                    FormBuilder.build('ktpl-currency-rate-conversion-configuration-form'),
                    $.get(Routing.generate('ktpl_currency_rate_conversion_get_currency_conversion_configuration'))
                ).then((form, response) => {
                    this.on('pim:controller:can-leave', function (event) {
                        form.trigger('pim_enrich:form:can-leave', event);
                    });
                    form.setData(response[0]);
                    form.setElement(this.$el).render();

                    return form;
                });
            }
        });
    }
);