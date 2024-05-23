<?php

add_shortcode('th_tickets', function ($atts) {
    $options = get_option('th_plus_options');
    $allow_export = isset($options['allow_export']) && $options['allow_export'] == 1;

    static $tickets_enqueue = false;

    $attributes = shortcode_atts(array(
        'user_id' => ''
    ), $atts);

    if (!$tickets_enqueue) {
        wp_enqueue_script('th-tickets-script', PLUGIN_ROOT . 'js/th-tickets.js', array('jquery'), '', true);
        wp_localize_script('th-tickets-script', 'ajax_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'user_id' => $attributes['user_id']
        ));
        wp_enqueue_style('th-tickets-style', PLUGIN_ROOT . 'css/th-tickets.css', array(), '', 'all');
        $tickets_enqueue = true;
    }

    ob_start();

    $status_choices = array(
        'New' => __('New', 'tickethub'),
        'Processing' => __('Processing', 'tickethub'),
        'Done' => __('Done', 'tickethub')
    );
    $type_choices = array(
        'Support' => __('Support', 'tickethub'),
        'Bug report' => __('Bug report', 'tickethub'),
        'Change request' => __('Change request', 'tickethub')
    );

    echo '<div class="th-ticket-controls">';
    echo '<input type="text" id="th-ticket-search" placeholder="' . __('Search', 'tickethub') . '">';
    echo '<div class="th-tickets-filter-container">';
    echo '<label for="th-toggle-archive" class="th-switch-container">' . __('Archive', 'tickethub');
    echo '<div class="th-switch">';
    echo '<input type="checkbox" id="th-toggle-archive">';
    echo '<span class="th-slider th-round"></span>';
    echo '</div>';
    echo '</label>';
    echo '<select id="th-ticket-status" class="th-select"><option value="">' . __('- Status -', 'tickethub') . '</option>';
    foreach ($status_choices as $value => $label) {
        echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
    }
    echo '</select>';
    echo '<select id="th-ticket-type" class="th-select"><option value="">' . __('- Type -', 'tickethub') . '</option>';
    foreach ($type_choices as $value => $label) {
        echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    if ($allow_export) {
        echo '<button id="th-export-tickets">' . __('Export Tickets', 'tickethub') . '</button>';
    }
    echo '</div>';

    echo '<table class="th-ticket-table"><thead><tr><th>' . __('ID', 'tickethub') . '</th><th>' . __('Status', 'tickethub') . '</th><th>' . __('Type', 'tickethub') . '</th><th>' . __('Date', 'tickethub') . '</th>';
    if (empty($attributes['user_id'])) {
        echo '<th>' . __('Issuer', 'tickethub') . '</th>';
    }
    echo '</tr></thead><tbody id="th-tickets-container">';
    echo '</tbody></table>';
    echo '<div id="th-ticket-pagination"></div>';

    return ob_get_clean();
});

function fetch_tickets_ajax()
{
    $is_archive = $_POST['isArchive'] === 'true';
    $search_value = sanitize_text_field($_POST['searchValue']);
    $status_value = sanitize_text_field($_POST['statusValue']);
    $type_value = sanitize_text_field($_POST['typeValue']);
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $user_id = intval($_POST['user_id']);

    $args = array(
        'post_type'      => 'th_ticket',
        'posts_per_page' => 10,
        'paged'          => $page,
        'post_status'    => $is_archive ? 'th_archive' : 'publish',
        'meta_query'     => array(
            'relation' => 'AND',
        )
    );

    if (!empty($search_value)) {
        $args['meta_query'][] = array(
            'key'     => 'th_ticket_id',
            'value'   => $search_value,
            'compare' => 'LIKE'
        );
    }
    if (!empty($status_value)) {
        $args['meta_query'][] = array(
            'key'     => 'th_ticket_status',
            'value'   => $status_value,
            'compare' => '='
        );
    }
    if (!empty($user_id)) {
        $args['author'] = $user_id;
    }
    if (!empty($type_value)) {
        $args['meta_query'][] = array(
            'key'     => 'th_ticket_type',
            'value'   => $type_value,
            'compare' => '='
        );
    }

    $the_query = new WP_Query($args);
    $output = '';

    while ($the_query->have_posts()) {
        $the_query->the_post();
        $post_id = get_the_ID();
        $ticket_id = esc_html(get_post_meta($post_id, 'th_ticket_id', true));
        $ticket_status = esc_html(get_post_meta($post_id, 'th_ticket_status', true));
        $ticket_type = esc_html(get_post_meta($post_id, 'th_ticket_type', true));
        $ticket_link = get_permalink();
        $ticket_date = get_the_date();
        $author_id = get_the_author_meta('ID');
        $first_name = get_the_author_meta('first_name', $author_id);
        $last_name = get_the_author_meta('last_name', $author_id);
        $ticket_author = $first_name . ' ' . $last_name;

        if (empty($first_name) && empty($last_name)) {
            $ticket_author = get_the_author_meta('display_name', $author_id);
        }

        $output .= "<tr>";
        $output .= "<td><span class='th-mobile-table-header'>" . __('ID', 'tickethub') . "</span><a href='$ticket_link'>$ticket_id</a></td>";
        $output .= "<td><span class='th-mobile-table-header'>" . __('Status', 'tickethub') . "</span><span class='th-status-chip' data-status='$ticket_status'>$ticket_status</span></td>";
        $output .= "<td><span class='th-mobile-table-header'>" . __('Type', 'tickethub') . "</span>$ticket_type</td>";
        $output .= "<td class='th-comment-date'><span class='th-mobile-table-header'>" . __('Date', 'tickethub') . "</span>$ticket_date</td>";
        if (empty($user_id)) {
            $output .= "<td><span class='th-mobile-table-header'>" . __('Created by', 'tickethub') . "</span>$ticket_author</td>";
        }
        $output .= "</tr>";
    }

    wp_reset_postdata();

    $pagination = paginate_links(array(
        'base'      => admin_url('admin-ajax.php') . '?page=%#%',
        'format'    => '%#%',
        'total'     => $the_query->max_num_pages,
        'current'   => $page,
        'mid_size'  => 2,
        'prev_next' => true,
        'prev_text' => __('Previous', 'tickethub'),
        'next_text' => __('Next', 'tickethub'),
        'end_size'  => 1,
        'type'      => 'array'
    ));

    $pagination_html = '';
    if (is_array($pagination)) {
        $pagination_html = "<div class='th-pagination-wrap'>";
        foreach ($pagination as $page) {
            if (strpos($page, 'current') !== false) {
                $pagination_html .= "<button class='th-page-number active'>" . strip_tags($page, '<a>') . "</button>";
            } else {
                $pagination_html .= "<button class='th-page-number'>" . strip_tags($page, '<a>') . "</button>";
            }
        }
        $pagination_html .= "</div>";
    }

    $final_output = json_encode(array('tickets' => $output, 'pagination' => $pagination_html));

    header('Content-Type: application/json');
    echo $final_output;
    die();
}
add_action('wp_ajax_fetch_tickets', 'fetch_tickets_ajax');
add_action('wp_ajax_nopriv_fetch_tickets', 'fetch_tickets_ajax');
