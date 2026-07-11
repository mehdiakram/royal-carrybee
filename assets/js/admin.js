/**
 * Royal Carrybee Admin JavaScript
 */
(function ($) {
    'use strict';

    var RCB_Admin = {
        init: function () {
            this.bindEvents();
            this.initDataTables();
            this.loadStores();
            this.loadCities();
            this.loadManualCities();
        },

        bindEvents: function () {
            // Settings form submit
            $('#rcb-settings-form').on('submit', this.saveSettings);

            // Test connection
            $('#rcb-test-connection').on('click', this.testConnection);

            // Refresh stores
            $('#rcb-refresh-stores').on('click', this.loadStores);

            // Copy button
            $('.rcb-copy-btn').on('click', this.copyToClipboard);

            // Add store modal
            $('#rcb-add-store').on('click', function () { $('#rcb-store-modal').show(); });
            $('.rcb-modal-close').on('click', function () { $(this).closest('.rcb-modal').hide(); });
            $('#rcb-store-form').on('submit', this.createStore);

            // Location cascading (Store)
            $('#store_city_id').on('change', this.loadZonesForStore);
            $('#store_zone_id').on('change', this.loadAreasForStore);

            // Location cascading (Manual Order)
            $('#rcb_manual_city').on('change', this.loadManualZones);
            $('#rcb_manual_zone').on('change', this.loadManualAreas);

            // Order actions
            $(document).on('click', '.rcb-sync-order, .rcb-sync-btn', this.syncOrder);
            $(document).on('click', '.rcb-cancel-btn', this.cancelOrder);
            $(document).on('click', '.rcb-create-btn', this.createOrder);
        },

        initDataTables: function () {
            if ($('#rcb-stores-table').length) {
                $('#rcb-stores-table').DataTable({
                    language: { emptyTable: 'No stores found. Add one above.' },
                    order: [[0, 'asc']]
                });
            }
            if ($('#rcb-orders-table').length) {
                $('#rcb-orders-table').DataTable({
                    order: [[6, 'desc']],
                    pageLength: 25
                });
            }
        },

        saveSettings: function (e) {
            e.preventDefault();
            var $form = $(this);
            var $btn = $form.find('button[type="submit"]');

            Swal.fire({ title: rcb_admin.i18n.saving, allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

            $.post(rcb_admin.ajax_url, $form.serialize() + '&action=rcb_save_settings', function (res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: rcb_admin.i18n.saved, timer: 1500, showConfirmButton: false });
                } else {
                    Swal.fire({ icon: 'error', title: rcb_admin.i18n.error, text: res.data });
                }
            });
        },

        testConnection: function () {
            var $btn = $(this);
            Swal.fire({ title: rcb_admin.i18n.testing, allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

            $.post(rcb_admin.ajax_url, { action: 'rcb_test_connection', nonce: rcb_admin.nonce }, function (res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: rcb_admin.i18n.connection_ok, text: 'Found ' + (res.data.data.cities ? res.data.data.cities.length : 0) + ' cities' });
                } else {
                    Swal.fire({ icon: 'error', title: rcb_admin.i18n.connection_fail, text: res.data });
                }
            });
        },

        loadStores: function () {
            $.post(rcb_admin.ajax_url, { action: 'rcb_get_stores', nonce: rcb_admin.nonce }, function (res) {
                if (res.success && res.data) {
                    var $table = $('#rcb-stores-table');
                    if ($table.length && $.fn.DataTable.isDataTable($table)) {
                        var dt = $table.DataTable();
                        dt.clear();
                        $.each(res.data, function (i, store) {
                            dt.row.add([
                                store.name,
                                store.contact_person_name,
                                store.contact_person_number,
                                store.address,
                                store.is_approved ? '<span class="rcb-status rcb-status-delivered">Approved</span>' : '<span class="rcb-status rcb-status-pending">Pending</span>',
                                store.is_default_pickup_store ? '✅' : ''
                            ]);
                        });
                        dt.draw();
                    }

                    var $select = $('#rcb_default_store');
                    if ($select.length) {
                        var savedVal = $select.data('saved');
                        console.log('RCB Saved Store ID:', savedVal);

                        $select.find('option:not(:first)').remove();
                        $.each(res.data, function (i, store) {
                            if (store.is_active || store.is_approved) {
                                var isSelected = (String(store.id) === String(savedVal));
                                $select.append('<option value="' + store.id + '"' + (isSelected ? ' selected' : '') + '>' + store.name + '</option>');
                            }
                        });

                        // If saved value matches nothing, select first option
                        if (savedVal && !$select.val()) {
                            $select.val(savedVal);
                        }
                    }
                }
            });
        },

        loadCities: function () {
            $.post(rcb_admin.ajax_url, { action: 'rcb_get_cities', nonce: rcb_admin.nonce }, function (res) {
                if (res.success && res.data && res.data.length > 0) {
                    var $select = $('#store_city_id');
                    $select.empty().append('<option value="">' + (rcb_admin.i18n.select_city || 'Select City') + '</option>');
                    $.each(res.data, function (i, city) {
                        $select.append('<option value="' + city.id + '">' + city.name + '</option>');
                    });
                } else if (!res.success) {
                    console.error('RCB Cities Error:', res.data);
                    if (typeof Swal !== 'undefined' && $('#store_city_id').length) {
                        Swal.fire({ icon: 'warning', title: 'Cities not loaded', text: res.data || 'Check API credentials', toast: true, position: 'top-end', timer: 3000, showConfirmButton: false });
                    }
                }
            }).fail(function (xhr, status, error) {
                console.error('RCB Cities AJAX Error:', error);
            });
        },

        loadZonesForStore: function () {
            var cityId = $(this).val();
            var $zone = $('#store_zone_id');
            var $area = $('#store_area_id');

            $zone.prop('disabled', true).empty().append('<option value="">Loading...</option>');
            $area.prop('disabled', true).empty().append('<option value="">Select Area</option>');

            if (!cityId) return;

            console.log('RCB Loading zones for city_id:', cityId);

            $.post(rcb_admin.ajax_url, { action: 'rcb_get_zones', nonce: rcb_admin.nonce, city_id: cityId }, function (res) {
                console.log('RCB Zones Response:', res);
                $zone.empty().append('<option value="">Select Zone</option>');
                if (res.success && res.data && res.data.length > 0) {
                    $.each(res.data, function (i, zone) {
                        $zone.append('<option value="' + zone.id + '">' + zone.name + '</option>');
                    });
                    $zone.prop('disabled', false);
                } else if (!res.success) {
                    console.error('RCB Zones Error:', res.data);
                    $zone.append('<option value="">Error: ' + (res.data || 'Unknown') + '</option>');
                } else {
                    $zone.append('<option value="">No zones found</option>');
                }
            }).fail(function (xhr, status, error) {
                console.error('RCB Zones AJAX Error:', error);
                $zone.empty().append('<option value="">AJAX Error</option>');
            });
        },

        loadAreasForStore: function () {
            var cityId = $('#store_city_id').val();
            var zoneId = $(this).val();
            var $area = $('#store_area_id');

            $area.prop('disabled', true).empty().append('<option value="">Loading...</option>');

            if (!zoneId) return;

            $.post(rcb_admin.ajax_url, { action: 'rcb_get_areas', nonce: rcb_admin.nonce, city_id: cityId, zone_id: zoneId }, function (res) {
                $area.empty().append('<option value="">Select Area</option>');
                if (res.success && res.data) {
                    $.each(res.data, function (i, area) {
                        $area.append('<option value="' + area.id + '">' + area.name + '</option>');
                    });
                    $area.prop('disabled', false);
                }
            });
        },

        createStore: function (e) {
            e.preventDefault();
            var $form = $(this);

            Swal.fire({ title: 'Creating store...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

            $.post(rcb_admin.ajax_url, $form.serialize() + '&action=rcb_create_store&nonce=' + rcb_admin.nonce, function (res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Store Created!', timer: 1500, showConfirmButton: false });
                    $('#rcb-store-modal').hide();
                    $form[0].reset();
                    RCB_Admin.loadStores();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.data });
                }
            });
        },

        syncOrder: function (e) {
            if (e && typeof e.preventDefault === 'function') e.preventDefault();
            var consignment = $(this).data('consignment');
            if (!consignment) {
                var $row = $(this).closest('tr');
                if ($row.length) {
                    consignment = $row.find('.rcb-sync-btn').attr('data-consignment') || $row.find('.rcb-status').text().replace('📦', '').trim();
                }
            }

            Swal.fire({ title: 'Syncing...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

            $.post(rcb_admin.ajax_url, { action: 'rcb_sync_order', nonce: rcb_admin.nonce, consignment_id: consignment }, function (res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Synced!', html: 'Status: <strong>' + res.data.transfer_status + '</strong>', timer: 2000 });
                    location.reload();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.data });
                }
            });
        },

        cancelOrder: function () {
            var orderId = $(this).data('order');

            Swal.fire({
                title: 'Cancel Order?',
                text: rcb_admin.i18n.confirm_cancel,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d63638',
                confirmButtonText: 'Yes, cancel it!'
            }).then(function (result) {
                if (result.isConfirmed) {
                    $.post(rcb_admin.ajax_url, { action: 'rcb_cancel_order', nonce: rcb_admin.nonce, order_id: orderId }, function (res) {
                        if (res.success) {
                            Swal.fire({ icon: 'success', title: 'Cancelled!', timer: 1500 });
                            location.reload();
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: res.data });
                        }
                    });
                }
            });
        },

        createOrder: function (e) {
            if (e && typeof e.preventDefault === 'function') e.preventDefault();
            var orderId = $(this).data('order');
            if (!orderId) {
                var $row = $(this).closest('tr');
                if ($row.length) {
                    orderId = $row.data('order_id') || $row.attr('id');
                    if (typeof orderId === 'string') {
                        orderId = orderId.replace('post-', '').replace('order-', '');
                    }
                }
            }
            var $btn = $(this);

            // Collect manual location data if present
            var manualCity = $('#rcb_manual_city').val();
            var manualZone = $('#rcb_manual_zone').val();
            var manualArea = $('#rcb_manual_area').val();

            if ($('#rcb_manual_city').length > 0) {
                if (!manualCity || !manualZone) {
                    Swal.fire({ icon: 'warning', title: 'Location Required', text: 'Please select City and Zone manually.' });
                    return;
                }
            }

            Swal.fire({
                title: 'Create Carrybee Order?',
                text: 'This will send the order to Carrybee for delivery.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#2271b1',
                confirmButtonText: 'Yes, create it!'
            }).then(function (result) {
                if (result.isConfirmed) {
                    $btn.prop('disabled', true).text('Creating...');
                    Swal.fire({ title: 'Creating order...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

                    var data = {
                        action: 'rcb_create_order',
                        nonce: rcb_admin.nonce,
                        order_id: orderId
                    };

                    if (manualCity) {
                        data.city_id = manualCity;
                        data.zone_id = manualZone;
                        data.area_id = manualArea;
                    }

                    $.post(rcb_admin.ajax_url, data, function (res) {
                        if (res.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Order Created!',
                                html: 'Consignment ID: <strong>' + res.data.consignment_id + '</strong>',
                                timer: 2000
                            });
                            setTimeout(function () { location.reload(); }, 2000);
                        } else {
                            $btn.prop('disabled', false).text('Create Carrybee Order');
                            Swal.fire({ icon: 'error', title: 'Error', text: res.data });
                        }
                    }).fail(function () {
                        $btn.prop('disabled', false).text('Create Carrybee Order');
                        Swal.fire({ icon: 'error', title: 'Error', text: 'AJAX request failed' });
                    });
                }
            });
        },

        loadManualCities: function () {
            if ($('#rcb_manual_city').length === 0) return;

            var $select = $('#rcb_manual_city');
            var $zone   = $('#rcb_manual_zone');
            var $area   = $('#rcb_manual_area');
            var addr    = (rcb_admin.order_address && rcb_admin.order_address.city_name) ? rcb_admin.order_address : null;

            $select.prop('disabled', true);

            $.post(rcb_admin.ajax_url, { action: 'rcb_get_cities', nonce: rcb_admin.nonce }, function (res) {
                $select.prop('disabled', false).find('option:not(:first)').remove();
                if (!res.success || !res.data) {
                    $select.find('option:first').text('Failed to load cities');
                    return;
                }
                $select.find('option:first').text('Select City/District');

                // Build city options
                $.each(res.data, function (i, city) {
                    $select.append('<option value="' + city.id + '">' + city.name + '</option>');
                });

                if (!addr) return;

                // Auto-match city from order address
                var cityName  = (addr.city_name  || '').toLowerCase().trim();
                var stateName = (addr.state_name || '').toLowerCase().trim();

                var bestCityId   = null;
                var bestCityName = null;
                var bestScore    = 0;

                $.each(res.data, function (i, city) {
                    var cn = city.name.toLowerCase().trim();
                    var score = 0;

                    // Exact match gets highest score
                    if (cn === cityName || cn === stateName) {
                        score = 100;
                    } else if (cityName && (cityName.indexOf(cn) !== -1 || cn.indexOf(cityName) !== -1)) {
                        score = 80;
                    } else if (stateName && (stateName.indexOf(cn) !== -1 || cn.indexOf(stateName) !== -1)) {
                        score = 70;
                    }

                    if (score > bestScore) {
                        bestScore    = score;
                        bestCityId   = city.id;
                        bestCityName = city.name;
                    }
                });

                if (bestCityId) {
                    $select.val(bestCityId).addClass('rcb-autofilled-field');
                    // Trigger zone loading with auto-fill
                    RCB_Admin.loadManualZonesWithAutofill(bestCityId, addr);
                }
            });
        },

        loadManualZones: function () {
            var cityId = $(this).val();
            RCB_Admin.loadManualZonesWithAutofill(cityId, null);
        },

        loadManualZonesWithAutofill: function (cityId, addr) {
            var $zone = $('#rcb_manual_zone');
            var $area = $('#rcb_manual_area');

            $zone.prop('disabled', true).find('option:not(:first)').remove();
            $area.prop('disabled', true).find('option:not(:first)').remove();

            if (!cityId) return;

            $.post(rcb_admin.ajax_url, { action: 'rcb_get_zones', nonce: rcb_admin.nonce, city_id: cityId }, function (res) {
                $zone.prop('disabled', false);
                if (!res.success || !res.data) return;

                $.each(res.data, function (i, zone) {
                    $zone.append('<option value="' + zone.id + '">' + zone.name + '</option>');
                });

                if (!addr) return;

                // Auto-match zone from order state/address2
                var stateName = (addr.state_name || '').toLowerCase().trim();
                var addr2     = (addr.address_2  || '').toLowerCase().trim();

                var bestZoneId   = null;
                var bestZoneName = null;
                var bestScore    = 0;

                $.each(res.data, function (i, zone) {
                    var zn = zone.name.toLowerCase().trim();
                    var score = 0;

                    if (zn === stateName || zn === addr2) {
                        score = 100;
                    } else if (stateName && (stateName.indexOf(zn) !== -1 || zn.indexOf(stateName) !== -1)) {
                        score = 80;
                    } else if (addr2 && (addr2.indexOf(zn) !== -1 || zn.indexOf(addr2) !== -1)) {
                        score = 70;
                    }

                    if (score > bestScore) {
                        bestScore    = score;
                        bestZoneId   = zone.id;
                        bestZoneName = zone.name;
                    }
                });

                if (bestZoneId) {
                    $zone.val(bestZoneId).addClass('rcb-autofilled-field');
                    // Show auto-fill badge & style
                    $('#rcb-autofill-badge').show();
                    $('.rcb-manual-location').addClass('rcb-autofilled');
                    // Try loading areas too
                    RCB_Admin.loadManualAreasAuto(cityId, bestZoneId);
                }
            });
        },

        loadManualAreas: function () {
            var zoneId = $(this).val();
            var cityId = $('#rcb_manual_city').val();
            RCB_Admin.loadManualAreasAuto(cityId, zoneId);
        },

        loadManualAreasAuto: function (cityId, zoneId) {
            var $area = $('#rcb_manual_area');

            $area.prop('disabled', true).find('option:not(:first)').remove();

            if (!zoneId || !cityId) return;

            $.post(rcb_admin.ajax_url, { action: 'rcb_get_areas', nonce: rcb_admin.nonce, city_id: cityId, zone_id: zoneId }, function (res) {
                $area.prop('disabled', false);
                if (res.success && res.data) {
                    $.each(res.data, function (i, area) {
                        $area.append('<option value="' + area.id + '">' + area.name + '</option>');
                    });
                }
            });
        },

        copyToClipboard: function () {
            var text = $(this).data('copy');
            navigator.clipboard.writeText(text).then(function () {
                Swal.fire({ icon: 'success', title: 'Copied!', timer: 1000, showConfirmButton: false, position: 'top-end', toast: true });
            });
        }
    };

    $(document).ready(function () {
        RCB_Admin.init();
    });

})(jQuery);
