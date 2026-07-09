/**
 * Royal Carrybee Checkout JavaScript
 */
(function ($) {
    'use strict';

    var RCB_Checkout = {
        init: function () {
            this.loadCities();
            this.bindEvents();
        },

        bindEvents: function () {
            // City change - load zones
            $(document.body).on('change select2:select', 'select[name="billing_rcb_city"], select[name="shipping_rcb_city"]', this.loadZones);

            // Zone change - load areas
            $(document.body).on('change select2:select', 'select[name="billing_rcb_zone"], select[name="shipping_rcb_zone"]', this.loadAreas);

            // Update on checkout update
            $(document.body).on('updated_checkout', function () {
                RCB_Checkout.loadCities();
            });
        },

        loadCities: function () {
            var $citySelects = $('select[name="billing_rcb_city"], select[name="shipping_rcb_city"]');

            if ($citySelects.length === 0) return;

            $citySelects.each(function () {
                var $select = $(this);
                // Only load if empty or has 1 option (placeholder)
                if ($select.find('option').length > 1) return;

                $select.addClass('rcb-loading');

                $.post(rcb_checkout.ajax_url, {
                    action: 'rcb_get_cities',
                    nonce: rcb_checkout.nonce
                }, function (res) {
                    $select.removeClass('rcb-loading');

                    if (res.success && res.data) {
                        $select.empty().append('<option value="">' + rcb_checkout.i18n.select_city + '</option>');

                        $.each(res.data, function (i, city) {
                            $select.append('<option value="' + city.id + '">' + city.name + '</option>');
                        });

                        // Trigger change to update Select2 if present
                        $select.trigger('change');
                    }
                });
            });
        },

        loadZones: function () {
            var $citySelect = $(this);
            var cityId = $citySelect.val();
            // Handle Select2 event object if present
            if (cityId && typeof cityId === 'object' && cityId.params) {
                cityId = cityId.params.data.id;
            }

            var prefix = $citySelect.attr('name').replace('_rcb_city', '');
            var $zoneSelect = $('select[name="' + prefix + '_rcb_zone"]');
            var $areaSelect = $('select[name="' + prefix + '_rcb_area"]');

            // Reset zone and area
            $zoneSelect.prop('disabled', true).empty().append('<option value="">' + rcb_checkout.i18n.loading + '</option>').trigger('change');
            $areaSelect.prop('disabled', true).empty().append('<option value="">' + rcb_checkout.i18n.select_area + '</option>').trigger('change');

            if (!cityId) {
                $zoneSelect.empty().append('<option value="">' + rcb_checkout.i18n.select_zone + '</option>').trigger('change');
                return;
            }

            $.post(rcb_checkout.ajax_url, {
                action: 'rcb_get_zones',
                nonce: rcb_checkout.nonce,
                city_id: cityId
            }, function (res) {
                $zoneSelect.empty().append('<option value="">' + rcb_checkout.i18n.select_zone + '</option>');

                if (res.success && res.data) {
                    $.each(res.data, function (i, zone) {
                        $zoneSelect.append('<option value="' + zone.id + '">' + zone.name + '</option>');
                    });
                    $zoneSelect.prop('disabled', false);
                }
                $zoneSelect.trigger('change');
            });
        },

        loadAreas: function () {
            var $zoneSelect = $(this);
            var zoneId = $zoneSelect.val();
            // Handle Select2 event object
            if (zoneId && typeof zoneId === 'object' && zoneId.params) {
                zoneId = zoneId.params.data.id;
            }

            var prefix = $zoneSelect.attr('name').replace('_rcb_zone', '');
            var cityId = $('select[name="' + prefix + '_rcb_city"]').val();
            var $areaSelect = $('select[name="' + prefix + '_rcb_area"]');

            $areaSelect.prop('disabled', true).empty().append('<option value="">' + rcb_checkout.i18n.loading + '</option>').trigger('change');

            if (!zoneId || !cityId) {
                $areaSelect.empty().append('<option value="">' + rcb_checkout.i18n.select_area + '</option>').trigger('change');
                return;
            }

            $.post(rcb_checkout.ajax_url, {
                action: 'rcb_get_areas',
                nonce: rcb_checkout.nonce,
                city_id: cityId,
                zone_id: zoneId
            }, function (res) {
                $areaSelect.empty().append('<option value="">' + rcb_checkout.i18n.select_area + '</option>');

                if (res.success && res.data) {
                    $.each(res.data, function (i, area) {
                        $areaSelect.append('<option value="' + area.id + '">' + area.name + '</option>');
                    });
                    $areaSelect.prop('disabled', false);
                }
                $areaSelect.trigger('change');
            });
        }
    };

    $(document).ready(function () {
        RCB_Checkout.init();
    });

})(jQuery);
