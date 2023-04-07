<?php
/**
 * This hook is only going to be useful if SAVE_QUERIES is set to true
 */

use Sentry\Tracing\SpanContext;

class Wp_Sentry_Performance_Hook_Sql{

    public static function add_hook(){
        add_filter('log_query_custom_data',['this','handle_hook'] , 11,5);
    }

    public function handle_hook ($query_data, $query = null, $query_time = null , $query_callstack = null , $query_start = null) {

        /**
         *  Sentry.io fails if this is too big. It shows no transaction.
         * @return int
         */
        function get_max_info_length(){
            return (int) $_GET['WP_SENTRY_SPAN_SIZE'] ?? 500;
        }
        $sqlSpan = new SpanContext();
        $sqlSpan->setOp('sql.query');
        if(is_string($query_callstack)){
            $callstack = implode(',',array_reverse(explode(',',$query_callstack)));
            $l = intval($_GET['WP_SENTRY_SPAN_SIZE'] ?? 500);
            $callstack = mb_strimwidth($callstack,0,get_max_info_length(),"...");
        }else{
            $callstack = 'error - no callstack: $query_callstack is not a string';
        }
        $newData['memory_peak_usage_FINISH'] = memory_get_peak_usage(true) / 1024 / 1024;
        $newData['sql.origin'] = $callstack;
        $sqlSpan->setData($newData);
        if(is_string($query)){
            $query = mb_strimwidth($query,0,get_max_info_length(),"...");
        }
        $sqlSpan->setDescription($query);
        $start = microtime(true);
        $sqlSpan->setStartTimestamp($start-$query_time);
        //finishes right away because we use the SQL timings of the query that just happened.
        $sqlSpan->setEndTimestamp($start);
        $parentSpan = \Sentry\SentrySdk::getCurrentHub()->getSpan();
        $parentSpan->startChild($sqlSpan);
        return $query_data;
    }
}


