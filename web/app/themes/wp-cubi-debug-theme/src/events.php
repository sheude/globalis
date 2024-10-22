<?php

use OpenSpout\Writer\Common\Creator\WriterEntityFactory;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Writer\XLSX\Options;

add_action('admin_post_export_registrations', 'export_event_registrations');

function export_event_registrations() {
    if (!current_user_can('manage_options')) {
        wp_die('Vous n\'avez pas les permissions nécessaires pour accéder à cette fonctionnalité.');
    }

    $event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

    if ($event_id === 0) {
        wp_die('Vous devez fournir l\'ID de l\'évènement');
    }

    $registrations = get_event_registrations($event_id);

    $filePath = sys_get_temp_dir() . '/export_event_' . $event_id . '.xlsx';

    $options = new Options();
    $writer = new Writer($options);
    $writer->openToFile($filePath);

    $header = ['Nom', 'Prénom', 'Email', 'Téléphone'];
    $writer->addRow(Row::fromValues($header));

    foreach ($registrations as $registration) {
        $row = [
            $registration->nom,
            $registration->prenom,
            $registration->email,
            $registration->telephone
        ];
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="inscriptions_event_' . $event_id . '.xlsx"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);

    unlink($filePath);
    exit;
}

function get_event_registrations($event_id) {
    $args = [
        'post_type'      => 'registrations',
        'posts_per_page' => -1,
        'meta_query'     => [
            [
                'key'     => 'registration_event_id',
                'value'   => $event_id,
                'compare' => '='
            ]
        ]
    ];

    $query = new WP_Query($args);
    
    if (!$query->have_posts()) {
        return [];
    }

    $registrations = [];
    while ($query->have_posts()) {
        $query->the_post();
        $registrations[] = (object) [
            'nom'       => get_post_meta(get_the_ID(), 'registration_last_name', true),
            'prenom'    => get_post_meta(get_the_ID(), 'registration_first_name', true),
            'email'     => get_post_meta(get_the_ID(), 'registration_email', true),
            'telephone' => get_post_meta(get_the_ID(), 'registration_phone', true)
        ];
    }

    wp_reset_postdata();

    return $registrations;
}