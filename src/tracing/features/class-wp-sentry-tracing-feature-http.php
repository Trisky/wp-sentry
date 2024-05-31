<?php

use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\UriInterface;
use Sentry\SentrySdk;
use Sentry\Tracing\SpanContext;

/**
 * @internal This class is not part of the public API and may be removed or changed at any time.
 */
class WP_Sentry_Tracing_Feature_HTTP extends AWP_Sentry_Tracing_Feature {
	use WP_Sentry_Tracks_Pushed_Scopes_And_Spans;

	public function __construct() {
		add_filter( 'pre_http_request', [ $this, 'handle_pre_http_request' ], 9999, 3 );
		add_action( 'http_api_debug', [ $this, 'handle_http_api_debug' ], 9999, 5 );
	}

	/** @param false|array|WP_Error $response */
	public function handle_pre_http_request( $response, array $parsed_args, string $url ) {
		// We expect the response to be `false` otherwise it was filtered and we should not create a span for that
		if ( $response !== false ) {
			return $response;
		}

		$parentSpan = SentrySdk::getCurrentHub()->getSpan();

		// If there is no sampled span there is no need to handle the event
		if ( $parentSpan === null || ! $parentSpan->getSampled() ) {
			return $response;
		}

		$method     = strtoupper( $parsed_args['method'] );
		$fullUri    = $this->get_full_uri( $url );
		$partialUri = $this->get_partial_uri( $fullUri );

		$context = $this->get_span_context_with_backtrace();
		$context->setOp( 'http.client' );
		$context->setDescription( $method . ' ' . $partialUri );
		$context->setData( array_merge( $context->getData(), [
			'url'                 => $partialUri,
			// See: https://develop.sentry.dev/sdk/performance/span-data-conventions/#http
			'http.query'          => $fullUri->getQuery(),
			'http.fragment'       => $fullUri->getFragment(),
			'http.request.method' => $method,
			// @TODO: Figure out how to get the request body size
			// 'http.request.body.size' => strlen( $parsed_args['body'] ?? '' ),
		]));

		$this->push_span( $parentSpan->startChild( $context ) );

		return $response;
	}

	/** @param array|WP_Error $response */
	public function handle_http_api_debug( $response, string $context, string $class, array $parsed_args, string $url ): void {
		$span = $this->maybe_pop_span();

		if ( $span === null ) {
			return;
		}
        if($response instanceof WP_Error){
            // WP_Error doesn't have any data on the error. At least if it's a timeout
            $response = null;
        }else{
            $response = $response['http_response'] ?? null;
        }

		if ( $response instanceof WP_HTTP_Requests_Response ) {
			$span->setHttpStatus( $response->get_status() );
			$span->setData( array_merge( $span->getData(), [
				// See: https://develop.sentry.dev/sdk/performance/span-data-conventions/#http
				'http.response.status_code' => $response->get_status(),
				'http.response.body.size'   => strlen( $response->get_data() ),
			] ) );
		}

		$span->finish();
	}

	/**
	 * Construct a full URI.
	 *
	 * @param string $url
	 *
	 * @return UriInterface
	 */
	private function get_full_uri( string $url ): UriInterface {
		return new Uri( $url );
	}

	/**
	 * Construct a partial URI, excluding the authority, query and fragment parts.
	 *
	 * @param UriInterface $uri
	 *
	 * @return string
	 */
	private function get_partial_uri( UriInterface $uri ): string {
		return (string) Uri::fromParts( [
			'scheme' => $uri->getScheme(),
			'host'   => $uri->getHost(),
			'port'   => $uri->getPort(),
			'path'   => $uri->getPath(),
		] );
	}

    protected function get_back_trace_level(): int
    {
        return 7;
    }
}
