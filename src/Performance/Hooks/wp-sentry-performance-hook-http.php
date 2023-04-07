<?php

class Wp_Sentry_Performance_Hook_Http
{

    public static function add_hook()
    {
        add_action('pre_http_request', ['this', 'pre_http_request'], 9999, 3);
        add_action('http_api_debug', ['this', 'http_api_debug'], 9999, 3);

    }


    public function pre_http_request($response, array $args, $url)
    {
        $backtrace = [];
        foreach (debug_backtrace() as $trace) {
            $class = $trace['class'] ?? $trace['file'] ?? 'unknown_class';
            $fn = $trace['function'] ?? 'no_function';
            $backtrace[] = "$class :: $fn";
        }
        $data = [
            'args' => $args,
            'url' => $url,
            'caller' => $backtrace
        ];
        $spanName = 'ajax';
        do_action('wp_sentry_span_start', 'ajax', $data);
        return $response;
    }

    public function http_api_debug($response = null, $type = null, $origin = null, $parsed_args = null, $url = null)
    {
        $new_data = ['response' => $response,
            'type' => $type,
            'origin' => $origin];
        do_action('wp_sentry_span_finish', $new_data);

    }

}