<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * Handle error report submission
 *
 * @package PhpMyAdmin
 */
/**
 * Gets some core libraries
 */
require_once 'libraries/common.inc.php';
include_once 'js/line_counts.php';

$submission_url = "http://reports.phpmyadmin.net/reports/submit";

$response = PMA_Response::getInstance();

if ($_REQUEST['send_error_report'] == true) {
    $response->addJSON('test', send_error_report(get_report_data(false)));
    if ($_REQUEST['automatic'] === "true") {
        $response->addJSON('message', PMA_Message::error(
            __('An error has been detected and an error report has been '
                .'automatically submitted based on your settings.')
            . '<br />'
            . __('You may want to refresh the page.')));
    } else {
        $response->addJSON('message', PMA_Message::success(
            __('Thank you for submitting this report.')
            . '<br />'
            . __('You may want to refresh the page.')));
        if($_REQUEST['always_send'] === "true") {
            PMA_persistOption("SendErrorReports", "always", "ask");
        }
    }
} elseif ($_REQUEST['get_settings']) {
    $response->addJSON('report_setting', $GLOBALS['cfg']['SendErrorReports']);
} else {
    $html = "";
    $html .= '<form action="error_report.php" method="post" name="report_frm"'
            .' id="report_frm" class="ajax">'
            .'<fieldset style="padding-top:0px">';

    $html .= '<p>'
            . __('Phpmyadmin has encountered an error. We have collected data about'
            .' this error as well as information about relevant configuration'
            .' settings to send to phpmyadmin for processing to help us in'
            .' debugging the problem')
            .'</p>';

    $html .= '<div class="label"><label><p>'
            . __('You may examine the data in the error report:')
            .'</p></label></div>'
            .'<textarea style="height:13em; overflow-y:scroll; width:570px" disabled>'
            .get_report_data()
            .'</textarea>';

    $html .= '<div class="label"><label><p>'
            . __('Please explain the steps that lead to the error:')
            .'</p></label></div>'
            .'<textarea style="height:10em; width:570px" name="description"'
            .'id="report_description"></textarea>';

    $html .= '<input type="checkbox" name="always_send"'
            .' id="always_send_checkbox"/>'
            .'<span>'
            . __('Automatically send report next time')
            .'</span>';

    $html .= '</fieldset>';

    $form_params = array(
        'db'    => $db,
        'table' => $table,
    );

    $html .= PMA_generate_common_hidden_inputs($form_params);
    $html .= PMA_getHiddenFields(get_report_data(false));

    $html .= '</form>';

    $response->addHTML($html);
}

/**
 * returns the error report data collected from the current configuration or
 * from the request parameters sent by the error reporting js code.
 *
 * @param boolean $json_encode whether to encode the array as a json string
 *
 * @return Array/String $report
 */
function get_report_data($json_encode = true) {
    $exception = $_REQUEST['exception'];
    $exception["stack"] = translate_stacktrace($exception["stack"]);
    $report = array(
        "exception" => $exception,
        "pma_version" => PMA_VERSION,
        "browser_name" => PMA_USR_BROWSER_AGENT,
        "browser_version" => PMA_USR_BROWSER_VER,
        "user_os" => PMA_USR_OS,
        "server_software" => $_SERVER['SERVER_SOFTWARE'],
        "user_agent_string" => $_SERVER['HTTP_USER_AGENT'],
        "locale" => $_COOKIE['pma_lang'],
        "url" => $_REQUEST['current_url'],
        "configuration_storage_enabled" =>
            !empty($GLOBALS['cfg']['Servers'][1]['pmadb']),
        "php_version" => phpversion(),
        "microhistory" => $_REQUEST['microhistory'],
    );

    if(!empty($_REQUEST['description'])) {
        $report['steps'] = $_REQUEST['description'];
    }

    if($json_encode) {
        return json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    } else {
        return $report;
    }
}

/**
 * Sends report data to the error reporting server
 *
 * @param Array $report the report info to be sent
 *
 * @return String $response the reply of the server
 */
function send_error_report($report) {
    global $submission_url;
    $data_string = json_encode($report);
    if (ini_get('allow_url_fopen') && false) {
        $context = array("http" =>
            array(
                'method'  => 'POST',
                'content' => $data_string,
            )
        );
        if (strlen($cfg['ProxyUrl'])) {
            $context['http'] = array(
                'proxy' => $cfg['ProxyUrl'],
                'request_fulluri' => true
            );
            if (strlen($cfg['ProxyUser'])) {
                $auth = base64_encode(
                    $cfg['ProxyUser'] . ':' . $cfg['ProxyPass']
                );
                $context['http']['header'] = 'Proxy-Authorization: Basic ' . $auth;
            }
        }
        $response = file_get_contents(
            $submission_url,
            false,
            stream_context_create($context)
        );
    } else if (function_exists('curl_init')) {
        $curl_handle = curl_init($submission_url);
        if (strlen($cfg['ProxyUrl'])) {
            curl_setopt($curl_handle, CURLOPT_PROXY, $cfg['ProxyUrl']);
            if (strlen($cfg['ProxyUser'])) {
                curl_setopt(
                    $curl_handle,
                    CURLOPT_PROXYUSERPWD,
                    $cfg['ProxyUser'] . ':' . $cfg['ProxyPass']
                );
            }
        }
        curl_setopt($curl_handle, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($curl_handle);
        curl_close($curl_handle);
    }
    return $response;
}

function translate_stacktrace($stack) {
    if(!defined('LINE_COUNTS')) {
        return $stack;
    }

    foreach($stack as &$level) {
        if(preg_match("<js/get_scripts.js.php\?(.*)>", $level["url"], $matches)) {
            parse_str($matches[1], $vars);
            List($file_name, $line_number) =
                    get_line_number($vars["scripts"], $level["line"]);
            unset($level["url"]);
            $level["filename"] = $file_name;
            $level["line"] = $line_number;
        }
    }
    unset($level);
    return $stack;
}

function get_line_number($filenames, $cumulative_number) {
  global $LINE_COUNT;
  $cumulative_sum = 0;
  foreach($filenames as $filename) {
    $filecount = $LINE_COUNT[$filename];
    if ($cumulative_number <= $cumulative_sum + $filecount + 2) {
      $linenumber = $cumulative_number - $cumulative_sum;
      break;
    }
    $cumulative_sum += $filecount + 2;
  }
  return array($filename, $linenumber);
}

?>
