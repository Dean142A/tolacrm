<?php
/**
 * Live Email Preview Modal Partial
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="woo-crm-email-preview-modal" class="woo-crm-modal">
    <div class="woo-crm-modal-content woo-crm-preview-modal-content">
        <div class="woo-crm-modal-header">
            <h2><span class="dashicons dashicons-email-alt"></span> <?php esc_html_e('Live Campaign Email Preview', 'woo-crm'); ?></h2>
            <button class="woo-crm-modal-close" id="btn-close-email-preview">&times;</button>
        </div>
        <div class="woo-crm-modal-body p-0">
            <div class="email-preview-subject-bar">
                <strong><?php esc_html_e('Subject:', 'woo-crm'); ?></strong> <span id="preview-subject-text">...</span>
            </div>
            <div class="email-preview-iframe-wrapper">
                <iframe id="crm-email-preview-iframe" frameborder="0"></iframe>
            </div>
        </div>
    </div>
</div>
