/**
 * WooCommerce CRM Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // 1. Chart.js Analytics Initialization
        if (typeof window.wooCrmAnalyticsData !== 'undefined' && $('#crm-chart-monthly-revenue').length) {
            var data = window.wooCrmAnalyticsData;

            if (window.wooCrmRevChartInstance) {
                window.wooCrmRevChartInstance.destroy();
            }
            if (window.wooCrmOrdChartInstance) {
                window.wooCrmOrdChartInstance.destroy();
            }

            // Monthly Revenue Chart
            var ctxRev = document.getElementById('crm-chart-monthly-revenue').getContext('2d');
            window.wooCrmRevChartInstance = new Chart(ctxRev, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Net Revenue ($)',
                        data: data.revenue,
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        fill: true,
                        tension: 0.3,
                        borderWidth: 3,
                        pointBackgroundColor: '#4f46e5'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                        x: { grid: { display: false } }
                    }
                }
            });

            // Monthly Orders Chart
            var ctxOrd = document.getElementById('crm-chart-monthly-orders').getContext('2d');
            window.wooCrmOrdChartInstance = new Chart(ctxOrd, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Order Count',
                        data: data.orders,
                        backgroundColor: '#3b82f6',
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        // 2. Merge Tag Pill Click Insertion Handler
        $(document).on('click', '.tag-pill-btn', function(e) {
            e.preventDefault();
            var tag = $(this).data('tag');
            var $textarea = $('#campaign_message');

            if ($textarea.length) {
                var start = $textarea[0].selectionStart;
                var end = $textarea[0].selectionEnd;
                var text = $textarea.val();

                $textarea.val(text.substring(0, start) + tag + text.substring(end));
                $textarea.focus();
                $textarea[0].selectionStart = $textarea[0].selectionEnd = start + tag.length;
            }
        });

        // 3. Live Email Preview Handler
        $(document).on('click', '.btn-preview-campaign', function(e) {
            e.preventDefault();

            var subject  = $('#campaign_subject').val();
            var message  = $('#campaign_message').val();
            var discount = $('#campaign_discount').val();

            if (!subject || !message) {
                alert('Please enter a subject line and message content to preview.');
                return;
            }

            var $modal = $('#woo-crm-email-preview-modal');
            $modal.addClass('active');

            $.post(wooCrmData.ajax_url, {
                action: 'woo_crm_preview_email',
                subject: subject,
                message: message,
                discount: discount,
                nonce: wooCrmData.nonce
            }, function(res) {
                if (res.success) {
                    $('#preview-subject-text').text(res.data.subject);
                    var iframe = document.getElementById('crm-email-preview-iframe');
                    var doc = iframe.contentDocument || iframe.contentWindow.document;
                    doc.open();
                    doc.write(res.data.html);
                    doc.close();
                } else {
                    alert('Error rendering email preview.');
                }
            });
        });

        $('#btn-close-email-preview').on('click', function() {
            $('#woo-crm-email-preview-modal').removeClass('active');
        });

        // 4. Manual Campaign Blast Form Submit Handler
        $('#woo-crm-manual-campaign-form').on('submit', function(e) {
            e.preventDefault();

            if (!confirm(wooCrmData.labels.confirm_send)) {
                return;
            }

            var $form = $(this);
            var $btn = $form.find('.btn-send-campaign');
            var $spinner = $('#campaign-spinner');

            $btn.prop('disabled', true);
            $spinner.addClass('is-active');

            var formData = $form.serializeArray();
            formData.push({ name: 'action', value: 'woo_crm_send_manual_campaign' });
            formData.push({ name: 'nonce', value: wooCrmData.nonce });

            $.post(wooCrmData.ajax_url, formData, function(res) {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');

                if (res.success) {
                    alert(res.data.message);
                    location.reload();
                } else {
                    alert(res.data.message || 'Error sending campaign blast.');
                }
            }).fail(function() {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
                alert('Server connection error.');
            });
        });

        // 5. Cart "Send Now" Action Button
        $(document).on('click', '.btn-send-now-cart', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var cartId = $btn.data('cart-id');

            $btn.prop('disabled', true).text(wooCrmData.labels.sending);

            $.post(wooCrmData.ajax_url, {
                action: 'woo_crm_send_now_cart',
                cart_id: cartId,
                nonce: wooCrmData.nonce
            }, function(res) {
                if (res.success) {
                    alert(res.data.message);
                    location.reload();
                } else {
                    alert(res.data.message || 'Error sending recovery email.');
                    $btn.prop('disabled', false).text('Send Now');
                }
            });
        });

        // 6. Cart "Clear" Action Button
        $(document).on('click', '.btn-clear-cart', function(e) {
            e.preventDefault();

            if (!confirm(wooCrmData.labels.confirm_clear)) {
                return;
            }

            var $btn = $(this);
            var cartId = $btn.data('cart-id');

            $.post(wooCrmData.ajax_url, {
                action: 'woo_crm_clear_cart',
                cart_id: cartId,
                nonce: wooCrmData.nonce
            }, function(res) {
                if (res.success) {
                    $('#crm-cart-row-' + cartId).fadeOut(300, function() { $(this).remove(); });
                } else {
                    alert(res.data.message || 'Error clearing cart.');
                }
            });
        });

        // 7. Settings Form Save Handler
        $('#woo-crm-settings-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $btn = $form.find('.btn-save-settings');
            var $spinner = $('#settings-spinner');

            $btn.prop('disabled', true);
            $spinner.addClass('is-active');

            var formData = $form.serializeArray();
            formData.push({ name: 'action', value: 'woo_crm_save_settings' });
            formData.push({ name: 'nonce', value: wooCrmData.nonce });

            $.post(wooCrmData.ajax_url, formData, function(res) {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');

                if (res.success) {
                    alert(wooCrmData.labels.saved);
                } else {
                    alert(res.data.message || 'Error saving settings.');
                }
            }).fail(function() {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
                alert('Server connection error.');
            });
        });

        // 8. Customer Profile Modal Handler
        $(document).on('click', '.btn-view-customer-profile', function(e) {
            e.preventDefault();

            var identifier = $(this).data('identifier');
            var $modal = $('#woo-crm-customer-modal');
            var $body  = $('#modal-customer-body');

            $modal.addClass('active');
            $body.html('<p style="padding:20px; text-align:center;">Loading customer details...</p>');

            $.post(wooCrmData.ajax_url, {
                action: 'woo_crm_get_customer_details',
                identifier: identifier,
                nonce: wooCrmData.nonce
            }, function(res) {
                if (res.success) {
                    var p = res.data;
                    var html = '<div class="customer-profile-detail">';
                    html += '<div style="margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;">';
                    html += '<div><strong>Email:</strong> ' + p.email + '</div>';
                    html += '<div><span class="woo-crm-badge segment-badge-' + p.segment + '">' + p.segment.toUpperCase() + '</span></div>';
                    html += '</div>';

                    if (p.tags && p.tags.length) {
                        html += '<div style="margin-bottom:15px;"><strong>Contact Tags:</strong> ';
                        $.each(p.tags, function(i, t) {
                            html += '<span class="contact-tag-pill">' + t + '</span> ';
                        });
                        html += '</div>';
                    }

                    html += '<div style="background:#f8fafc; padding:15px; border-radius:6px; margin-bottom:20px; display:flex; gap:20px;">';
                    html += '<div><strong>Total Orders:</strong> ' + p.summary.total_orders + '</div>';
                    html += '<div><strong>Lifetime Value:</strong> $' + p.summary.ltv.toFixed(2) + '</div>';
                    html += '<div><strong>Avg Order:</strong> $' + p.summary.avg_order.toFixed(2) + '</div>';
                    html += '</div>';

                    if (p.orders && p.orders.length) {
                        html += '<h4>Order History</h4>';
                        html += '<table class="woo-crm-table mb-20"><thead><tr><th>Order #</th><th>Status</th><th>Total</th><th>Date</th></tr></thead><tbody>';
                        $.each(p.orders, function(i, o) {
                            html += '<tr><td>#' + o.id + '</td><td>' + o.status + '</td><td>$' + parseFloat(o.total).toFixed(2) + '</td><td>' + o.date + '</td></tr>';
                        });
                        html += '</tbody></table>';
                    }

                    if (p.campaign_history && p.campaign_history.length) {
                        html += '<h4>Campaign History</h4>';
                        html += '<table class="woo-crm-table"><thead><tr><th>Campaign</th><th>Coupon</th><th>Sent At</th></tr></thead><tbody>';
                        $.each(p.campaign_history, function(i, c) {
                            html += '<tr><td>' + c.campaign_type + '</td><td>' + (c.coupon_code || '—') + '</td><td>' + c.sent_at + '</td></tr>';
                        });
                        html += '</tbody></table>';
                    }

                    html += '</div>';
                    $body.html(html);
                } else {
                    $body.html('<p style="color:red;">Failed to load profile.</p>');
                }
            });
        });

        $('.woo-crm-modal-close').on('click', function() {
            $('#woo-crm-customer-modal').removeClass('active');
        });

    });

})(jQuery);
