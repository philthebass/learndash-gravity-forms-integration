<?php
/*
Plugin Name: LearnDash Gravity Forms Integration
Description: A plugin to integrate LearnDash with Gravity Forms to hide LearnDash Buttons when required.
Version: 1.0
Author: Phil Evans Rubber Duck Digital
*/
// Your plugin code here

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Add custom option to Gravity Forms settings

add_filter('gform_form_settings', 'cwpai_add_hide_learndash_buttons_setting', 10, 2);
function cwpai_add_hide_learndash_buttons_setting($settings, $form) {
    $settings['Form Options']['hide_learndash_buttons'] = '
    <tr>
        <th>
            <label for="hide_learndash_buttons">Hide LearnDash Buttons</label>
        </th>
        <td>
            <select id="hide_learndash_buttons" name="hide_learndash_buttons">
                <option value="no" '.selected(rgar($form, 'hide_learndash_buttons'), 'no', false).'>No</option>
                <option value="yes" '.selected(rgar($form, 'hide_learndash_buttons'), 'yes', false).'>Yes</option>
            </select>
            <p class="description">Choose "Yes" to hide the LearnDash Next Topic or Next Lesson buttons when this form is displayed.</p>
        </td>
    </tr>';
    return $settings;
}

// Save the custom setting
add_filter('gform_pre_form_settings_save', 'cwpai_save_hide_learndash_buttons_setting');
function cwpai_save_hide_learndash_buttons_setting($form) {
    error_log('Before saving: ' . print_r($form, true));
    $form['hide_learndash_buttons'] = rgpost('hide_learndash_buttons');
    error_log('After saving: ' . print_r($form, true));
    return $form;
}

// Hide LearnDash buttons if the Gravity Form setting is set to Yes
add_action('wp_head', 'hide_learndash_buttons_if_gravity_form_setting');
function hide_learndash_buttons_if_gravity_form_setting() {
    if (is_singular('sfwd-topic')) {
        global $post;
        $content = $post->post_content;

        if (has_shortcode($content, 'gravityform')) {
            preg_match('/\[gravityform.*id="(\d+)".*\]/', $content, $matches);
            if (isset($matches[1])) {
                $form_id = $matches[1];
                $form = GFAPI::get_form($form_id);

                if ($form && isset($form['hide_learndash_buttons']) && $form['hide_learndash_buttons'] == 'yes') {
                    echo '<style>
                        .ld-content-actions {
                            display: none !important;
                        }
                    </style>';
                }
            }
        }
    }
}



// Hook into Gravity Forms submission
add_action('gform_after_submission', 'cwpai_redirect_to_next_learndash_topic', 10, 2);
function cwpai_redirect_to_next_learndash_topic($entry, $form) {
    // Check if the hide_learndash_buttons setting is set to yes
    if (isset($form['hide_learndash_buttons']) && $form['hide_learndash_buttons'] == 'yes') {
        // Get the current user ID
        $user_id = get_current_user_id();

        // Get the current post ID (assuming the form is embedded in a LearnDash topic or lesson)
        $current_post_id = get_the_ID();

        // Get the next LearnDash topic or lesson
        $next_post_id = cwpai_learndash_next_post_link($current_post_id, $user_id);

        // If a next post is found, redirect the user
        if ($next_post_id) {
            $next_post_url = get_permalink($next_post_id);
            if (!headers_sent()) {
                wp_redirect($next_post_url);
                exit;
            } else {
                echo "<script type='text/javascript'>window.location.href='$next_post_url';</script>";
                exit;
            }
        }
    }
}

// Function to get the next LearnDash topic or lesson
function cwpai_learndash_next_post_link($current_post_id, $user_id) {
    // Get the course ID
    $course_id = learndash_get_course_id($current_post_id);

    // Get the course steps
    $course_steps = learndash_course_get_steps_by_type($course_id, 'sfwd-topic');

    // Find the current step index
    $current_step_index = array_search($current_post_id, $course_steps);

    // Get the next step ID
    if ($current_step_index !== false && isset($course_steps[$current_step_index + 1])) {
        return $course_steps[$current_step_index + 1];
    }

    return false;
}

// Ensure the form settings are available in the shortcode
add_filter('gform_pre_render', 'cwpai_gform_pre_render');
add_filter('gform_pre_submission_filter', 'cwpai_gform_pre_render');
add_filter('gform_admin_pre_render', 'cwpai_gform_pre_render');
function cwpai_gform_pre_render($form) {
    // Check if the hide_learndash_buttons setting is set to yes
    if (isset($form['hide_learndash_buttons']) && $form['hide_learndash_buttons'] == 'yes') {
        // Inject CSS to hide LearnDash buttons
        add_action('wp_footer', function() {
            echo '<style>
                .learndash_next_prev_link {
                    display: none !important;
                }
            </style>';
        });
    }
    return $form;
}