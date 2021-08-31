'use strict';
define([
  'jquery',
  'underscore',
  'oro/translator',
  'pim/job/common/edit/launch',
  'routing',
  'pim/router',
  'pim/common/property',
  'oro/messenger',
  'oro/loading-mask',
  'pim/template/export/common/edit/launch',
], function ($, _, __, BaseForm, Routing, router, propertyAccessor, messenger, LoadingMask, template) {
  return BaseForm.extend({

    /**
     * Launch the job
     */
    launch: function () {
      var loadingMask = new LoadingMask();
      loadingMask.render().$el.appendTo(this.getRoot().$el).show();
      $.post(this.getUrl())
        .then(function (response) {
          messenger.notify('success', __('ktpl.currency_rate_conversion.form.job_instance.launch.success'));
        })
        .fail(function () {
          messenger.notify('error', __('pim_import_export.form.job_instance.fail.launch'));
        })
        .always(function () {
          loadingMask.hide().$el.remove();
        });
    },

    /**
     * Get the route to launch the job
     *
     * @return {string}
     */
    getUrl: function () {
      return Routing.generate(this.config.route);
    },
  });
});
