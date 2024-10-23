<?php

namespace Globalis\WP\Test;

define('REGISTRATION_ACF_KEY_LAST_NAME', 'field_64749cfff238e');
define('REGISTRATION_ACF_KEY_FIRST_NAME', 'field_64749d4bf238f');
define('REGISTRATION_ACF_KEY_EMAIL', 'field_64749d780cd14');
define('REGISTRATION_ACF_KEY_EVENT_ID', 'field_64749cde33fd7');
define('EVENT_ACF_KEY_DATE', 'field_64749adbfcbda');
define('EVENT_ACF_KEY_TIME', 'field_64749b6868643');
define('EVENT_ACF_KEY_FILE', 'field_64749bea84f58');

add_filter('wp_insert_post_data', __NAMESPACE__ . '\\save_auto_title', 99, 2);
add_action('edit_form_after_title', __NAMESPACE__ . '\\display_custom_title_field');
add_action('acf/save_post', __NAMESPACE__ . '\\send_inscription_email', 20);

function save_auto_title($data, $postarr)
{
    if (! $data['post_type'] === 'registrations') {
        return $data;
    }
    if ('auto-draft' == $data['post_status']) {
        return $data;
    }

    if (!isset($postarr['acf'][REGISTRATION_ACF_KEY_LAST_NAME]) || !isset($postarr['acf'][REGISTRATION_ACF_KEY_FIRST_NAME])) {
        return $data;
    }

    $data['post_title'] = "#" . $postarr['ID'] .  " (" . $postarr['acf'][REGISTRATION_ACF_KEY_LAST_NAME] . " " . $postarr['acf'][REGISTRATION_ACF_KEY_FIRST_NAME] . ")";

    $data['post_name']  = wp_unique_post_slug(sanitize_title(str_replace('/', '-', $data['post_title'])), $postarr['ID'], $postarr['post_status'], $postarr['post_type'], $postarr['post_parent']);

    return $data;
}

function display_custom_title_field($post)
{
    if ($post->post_type !== 'registrations' || $post->post_status === 'auto-draft') {
        return;
    }
    ?>
    <h1><?= $post->post_title ?></h1>
    <?php
}

function send_inscription_email($post_id)
{
    if (get_post_type($post_id) !== 'registrations') {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || get_post_meta($post_id, '_edit_lock', true)) {
        return;
    }
    
    $last_name  = get_field(REGISTRATION_ACF_KEY_LAST_NAME, $post_id);
    $first_name = get_field(REGISTRATION_ACF_KEY_FIRST_NAME, $post_id);
    $email      = get_field(REGISTRATION_ACF_KEY_EMAIL, $post_id);
    $event_id   = get_field(REGISTRATION_ACF_KEY_EVENT_ID, $post_id);
    
    $event_date    = get_field(EVENT_ACF_KEY_DATE, $event_id);
    $event_time    = get_field(EVENT_ACF_KEY_TIME, $event_id);
    $event_file_id = get_field(EVENT_ACF_KEY_FILE, $event_id);
    
    $formatted_date = date_i18n('d/m/Y', strtotime($event_date));
    $formatted_time = date_i18n('H\hi', strtotime($event_time));
    $event_file_url = wp_get_attachment_url($event_file_id);
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'To: ' . $first_name . ' ' . $last_name . ' <' . $email . '>',
        'From: ' . WP_MAIL_FROM_NAME . ' <' . WP_MAIL_FROM_EMAIL . '>'
    );
    
    $subject  = 'Votre inscription notre l\'événement';
    $message  = 'Bonjour ' . $first_name . ' ' . $last_name . ",\n\n";
    $message .= "Vous êtes inscrit à l'événement prévu pour le " . $formatted_date . " à " . $formatted_time . ".\n";
    $message .= "Veuillez trouver votre billet d'entrée en pièce jointe.\n\n";
    $message .= "Merci et à bientôt !";
    
    $attachments = [];
    if ($event_file_url) {
        $event_file_path = get_attached_file($event_file_id);
        if (file_exists($event_file_path)) {
            $attachments[] = $event_file_path;
        }
    }
    
    if (!wp_mail($email, $subject, nl2br($message), $headers, $attachments)) {
        error_log('Échec de l\'envoi de l\'email à ' . $email);
    }
}