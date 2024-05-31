<?php

use Sentry\Tracing\SpanContext;

abstract class AWP_Sentry_Tracing_Feature
{
    /**
     * To find the correct value for your implementation: Set the return value
     * to 1 and inspect the debug_backtrace array inside get_span_context_with_backtrace
     *
     * @return int:  The index of the relevant 'caller' in the debug_backtrace()
     */
    abstract protected function get_back_trace_level(): int;
    protected function get_span_context_with_backtrace(): SpanContext
    {

        $level = $this->get_back_trace_level();
        $relevant_trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $level+1) [$level];
        $context = new SpanContext;
        $context->setData([
            'code.filepath' => $relevant_trace['file'] ?? null,
            'code.function' => $relevant_trace['function'] ?? null,
            'code.lineno' => $relevant_trace['line'] ?? null,
        ]);
        return $context;
    }

}