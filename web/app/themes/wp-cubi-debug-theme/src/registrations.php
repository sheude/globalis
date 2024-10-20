<?php

namespace Globalis\WP\Test;

define('REGISTRATION_ACF_KEY_LAST_NAME', 'field_64749cfff238e');
define('REGISTRATION_ACF_KEY_FIRST_NAME', 'field_64749d4bf238f');
define('REGISTRATION_ACF_KEY_EMAIL', 'field_64749d780cd14');
define('REGISTRATION_ACF_KEY_PHONE', 'field_64749d920cd15');
define('REGISTRATION_ACF_KEY_EVENT_ID', 'field_64749cde33fd7');
define('EVENT_ACF_KEY_DATE', 'field_64749adbfcbda');
define('EVENT_ACF_KEY_TIME', 'field_64749b6868643');

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
    // Vérifier que le post est de type 'registrations'
    if (get_post_type($post_id) !== 'registrations') {
        return;
    }

    // Vérifier si c'est une mise à jour / révision ou une sauvegarde automatique
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || get_post_meta($post_id, '_edit_lock', true)) {
        return;
    }
    
    // Récupérer les informations de l'inscription via ACF
    $last_name  = get_field(REGISTRATION_ACF_KEY_LAST_NAME, $post_id);
    $first_name = get_field(REGISTRATION_ACF_KEY_FIRST_NAME, $post_id);
    $email      = get_field(REGISTRATION_ACF_KEY_EMAIL, $post_id);
    $phone      = get_field(REGISTRATION_ACF_KEY_PHONE, $post_id);
    $event_id   = get_field(REGISTRATION_ACF_KEY_EVENT_ID, $post_id);
    
    // Récupérer les détails de l'événement lié via ACF
    $event_date = get_field(EVENT_ACF_KEY_DATE, $event_id);
    $event_time = get_field(EVENT_ACF_KEY_TIME, $event_id);
    $event_name = get_the_title($event_id);
    
    // Créer un tableau avec toutes les informations de l'inscription et de l'événement
    $registration_data = [
        'last_name'  => $last_name,
        'first_name' => $first_name,
        'email'      => $email,
        'phone'      => $phone,
        'event'      => [
            'id'   => $event_id,
            'name' => $event_name,
            'date' => $event_date,
            'time' => $event_time,
        ],
    ];
    
    // Générer le PDF et obtenir l'URL
    $pdf_url = generate_pdf_ticket($registration_data);
    
    // Log success et URL du PDF
    error_log('Lien du PDF : ' . $pdf_url);
    
    // Retourner ou utiliser l'URL du PDF ici
    return $pdf_url;
}

function generate_pdf_ticket($data)
{
    // Inclure FPDF si nécessaire
    require_once __DIR__ . '/../lib/fpdf/fpdf.php';  // Chemin vers FPDF
    
    // Convertir toutes les données à l'encodage ISO-8859-1
    $data = convert_to_pdf_encoding($data);
    
    // Créer un nouveau document PDF
    $pdf = new \FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    
    // Titre avec encodage corrigé
    $pdf->Cell(40, 10, mb_convert_encoding('Billet d\'entrée', 'ISO-8859-1', 'UTF-8'));
    $pdf->Ln();
    
    // Ajouter les détails de l'inscription avec encodage corrigé
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(40, 10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Nom : ' . $data['first_name'] . ' ' . $data['last_name']));
    
    // Si le numéro de téléphone est renseigné, l'afficher
    if (!empty($data['phone'])) {
        $pdf->Ln();
        $pdf->Cell(40, 10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Téléphone : ' . $data['phone']));
    }

    $pdf->Ln();
    $pdf->Cell(40, 10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Événement : ' . $data['event']['name'])); // Nom de l'événement dans le PDF
    $pdf->Ln();
    $pdf->Cell(40, 10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Prévu le ' . $data['event']['date'] . ' à ' . $data['event']['time']));

    
    // Définir le chemin d'enregistrement du PDF
    $upload_dir = wp_upload_dir();
    
    // Générer un nom de fichier unique avec ID et horodatage
    $clean_last_name = sanitize_file_name($data['last_name']);
    $clean_first_name = sanitize_file_name($data['first_name']);
    $clean_event_name = sanitize_file_name($data['event']['name']);
        
    $pdf_filename = $clean_last_name . '_' . $clean_first_name . '-' . $clean_event_name . '-' . date('Ymd_His') . '.pdf';
    
    $pdf_path = $upload_dir['path'] . '/' . $pdf_filename;  // Chemin physique du fichier
    $pdf_url = $upload_dir['url'] . '/' . $pdf_filename;    // URL publique du fichier
    
    // Sauvegarder le fichier PDF
    $pdf->Output('F', $pdf_path);
    
    // Retourner le lien URL du fichier PDF
    return $pdf_url;
}

function convert_to_pdf_encoding($data)
{
    // Parcourt chaque élément du tableau et convertit en ISO-8859-1
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            // Si c'est un sous-tableau, on applique récursivement la fonction
            $data[$key] = convert_to_pdf_encoding($value);
        } else {
            // Convertir la chaîne en ISO-8859-1
            // $data[$key] = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $value);
            mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
        }
    }
    return $data;
}

// // Préparer l'email
// $subject = 'Votre inscription à l\'événement';
// $message = 'Bonjour ' . $first_name . ' ' . $last_name . ",\n\n";
// $message .= "Vous êtes inscrit à l'événement prévu pour le " . $event_date . " à " . $event_time . ".\n";
// $message .= "Veuillez trouver votre billet d'entrée en pièce jointe.\n\n";
// $message .= "Merci et à bientôt !";
// $message .= "L'email " . $email;

// // En-têtes pour l'email
// $headers = array('Content-Type: text/html; charset=UTF-8');

// // Envoyer l'email avec le billet PDF en pièce jointe
// if (wp_mail($email, $subject, nl2br($message), $headers)) {
//     error_log('Email envoyé avec succès à ' . $email);
// } else {
//     error_log('Échec de l\'envoi de l\'email à ' . $email);
// }