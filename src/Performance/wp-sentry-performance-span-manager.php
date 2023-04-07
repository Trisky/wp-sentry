<?php

use Sentry\Tracing\SpanContext;

class Wp_Sentry_Performance_Span_Manager {
    
    static $sentry_spans = [];

    /**
     * @param string $name
     * @param $data
     * @param $description
     * @return void
     */
    public static function start(string $name, $description = '', $data = [], ){
        $parentSpan = \Sentry\SentrySdk::getCurrentHub()->getSpan();
        $cacheSpan = new SpanContext();
        $data['memory_peak_usage_START'] = memory_get_peak_usage(true) / 1024 / 1024;
        $cacheSpan->setData($data);
        if(is_string($description) && $description){
            $cacheSpan->setDescription($description);
        }
        $cacheSpan->setStartTimestamp(microtime(true));
        $cacheSpan->setOp($name);
        $appSpan =  $parentSpan->startChild($cacheSpan);
        $id = $parentSpan->getSpanId()->__toString();
        self::$sentry_spans[$id] = $parentSpan;
        \Sentry\SentrySdk::getCurrentHub()->setSpan($appSpan);
    }

    /**
     * @param $newData - To add new data to the Sentry span that might be relevant for debugging.
     * @param $newName - To change the spans name post factum.
     * @return void
     */
    public static function finish(array $newData = [], string $newName = '')
    {
        $appSpan = \Sentry\SentrySdk::getCurrentHub()->getSpan();
        if ($newName) {
            $appSpan->setOp($newName);
        }
        $oldData = $appSpan->getData();
        $newData['memory_peak_usage_FINISH'] = memory_get_peak_usage(true) / 1024 / 1024;
        $appSpan->setData(array_merge($oldData, $newData));
        $appSpan->finish();
        $id = $appSpan->getParentSpanId()->__toString();
        if(isset(self::$sentry_spans[$id])){
            $parent = self::$sentry_spans[$id];
            \Sentry\SentrySdk::getCurrentHub()->setSpan($parent);
        }else{
            /**
             * Error!
             * we need to handle these cases, because somebody might have called start but then forgot to call finish.
             */
            $parent = self::$sentry_spans['main_transaction'];
            \Sentry\SentrySdk::getCurrentHub()->setSpan($parent);
            do_action('wp_sentry_span_start', "Previous span was already finished - ID:$id");

            do_action('wp_sentry_span_finish');
        }
    }
}

add_action('wp_sentry_span_start',function($name,$data = [], $description = ''){
    \Wp_Sentry_Performance_Span_Manager::start($name, $description, $data);
});
add_action('wp_sentry_span_finish',function($newData = [], $newName = ''){
    if(!is_array($newData)){
        $newData = [];
    }
    \Wp_Sentry_Performance_Span_Manager::finish($newData, $newName);
});