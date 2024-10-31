<?php
/**
 * Plugin Name:       Muscula	
 * Plugin URI:        https://www.muscula.com
 * Description:       Monitoring and logging platform
 * Version:           1.0.2
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

$option_name = "muscula_log_id";

add_action('admin_menu', 'muscula_logger_plugin_menu');

function muscula_logger_plugin_menu()
{
    add_options_page('Muscula Logging Options', __('Muscula Logging', 'muscula-logger-plugin'), 'manage_options', 'muscula-logger-plugin', 'muscula_plugin_options');
}

add_action('admin_head', function () {
    ?>
    <style>
        #muscula-logger-wrapper {
            background-color: #2e2e2e;
            width: 50%;
            padding: 20px;
            margin-top: 20px;
            text-align: center;
            border-radius: .25rem;
        }

        #muscula-logger-submit-button {
            background-color: rgb(130, 177, 57) !important;
            color: white;
            padding: .25rem .75rem;
            border-radius: .25rem;
        }

        #muscula-logger-wrapper img {
            width: 50%;
        }

        #muscula-logger-wrapper a {
            color: rgb(130, 177, 57);
        }

        #muscula-logger-wrapper input {
            background-color: #2e2e2e;
            color: white;
        }

        #muscula-logger-form {
            margin: 100px auto;
            margin-top: 30px;
            background-color: #fff;
            display: flex;
            flex-flow: column;
            max-width: 400px;
            box-sizing: border-box;
            padding: 20px;
            font-size: 18px;
            color: #000;
        }

        #muscula-logger-form label {
            padding-bottom: 15px;
        }

        #muscula-logger-form button {
            background-color: transparent;
            margin-top: 25px;
            text-transform: uppercase;
            outline: none;
            border: none;
            width: fit-content;
            transition: .2s ease-out all;
            margin: 25px auto 0 auto;
        }

        #muscula-logger-form button:hover {
            transform: scale(1.1);
            cursor: pointer;
        }

        #muscula-logger-form input {
            border-radius: 0;
            border: none;
            outline: none;
            border-bottom: 2px solid #444;
            font-size: 18px;
        }
    </style>
    <?php
});

function muscula_plugin_options()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'muscula-logger-plugin'));
    }

    global $option_name;

    $input_name = "muscula_log_numer";
    $log_id = get_option($option_name);
    echo '<div id="muscula-logger-wrapper">
        <img src="'.plugin_dir_url( __FILE__ ).'logo_dark_muscula.svg" alt="Muscula"/>';

    if (isset($_POST[$input_name])) {
        $log_id = sanitize_text_field($_POST[$input_name]);

        if (strlen($log_id) > 50) {
            $log_id = '';
        }
        update_option($option_name, $log_id);

        echo '<div class="updated">' . __('Settings saved', 'muscula-logger-plugin') . '</div>';
    }

    echo '<form method="post" id="muscula-logger-form" style="background-color: #373737; width: 100%; color: white;">';
    echo '<label for="' . $input_name . '">' . __('Muscula Log ID', 'muscula-logger-plugin') . '</label>';
    echo '<p>Create new Project and new Log in <a target="_blank" href="https://app.muscula.com">Muscula Application</a>, copy from there your LogId</p>';
    echo '<input type="text" name="' . $input_name . '" value="' . esc_html__($log_id) . '" id="' . $input_name . '"/>';
    echo '<button id="muscula-logger-submit-button" type="submit">' . __('Save', 'muscula-logger-plugin') . '</button>';
    echo '</form>';
    echo '</div>';
}

function add_muscula_js()
{
    global $option_name;
    $log_id = get_option($option_name);

    if (!empty($log_id)) {

        echo <<<END
  <script type="text/javascript">
  window.Muscula = {
      settings: {
          logId: '$log_id',
      },
  };
  (function () {
      var m = document.createElement('script');
      m.type = 'text/javascript';
      m.async = true;
      m.src = 'https://www.muscula.com/m2v1.min.js';
      var s = document.getElementsByTagName('script')[0];
      s.parentNode.insertBefore(m, s);
      window.Muscula.run = function () {
          var a;
          eval(arguments[0]);
          window.Muscula.run = function () {};
      };
      window.Muscula.errors = [];
      window.onerror = function () {
          window.Muscula.errors.push(arguments);
          return window.Muscula.settings.suppressErrors === undefined;
      };
  })();
  </script>
END;
    }
}

add_action('wp_head', 'add_muscula_js');

require 'vendor/autoload.php';
global $option_name;
$log_id = get_option($option_name);

if (!empty($log_id)) {
    $handler = Muscula\Handler::getInstance();
    $handler->start($log_id); // initialize handlers, provide logId

}
