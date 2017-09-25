<?php namespace Nticaric\Twitter;

use GuzzleHttp\Client;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Stream\Utils;
use GuzzleHttp\Event\AbstractTransferEvent;
use GuzzleHttp\Subscriber\Log\LogSubscriber;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Middleware;

class TwitterStream {

    private $endpoint  = "https://stream.twitter.com/1.1/";
    private $retries   = 16;
    private $log       = true;
    private $logger    = null;
    private $formatter = null;
    
    public function __construct($config) {

		$auth = new Oauth1([
            'consumer_key' => $config['consumer_key'],
            'consumer_secret' => $config['consumer_secret'],
            'token' => $config['token'],
            'token_secret' => $config['token_secret']

        ]);
		
		//mmsa12
		//Guzzle 6 does not support RetrySubscriber
		// upgrade the code to match Middleware
		/*
        $retry = new RetrySubscriber([
            'filter' => RetrySubscriber::createStatusFilter([503]),
            'max'    => $this->retries,
        ]);
		*/
		$handlerStack = HandlerStack::create( new CurlHandler() );
		$handlerStack->push($auth);
		$handlerStack->push( Middleware::retry( $this->retryDecider(), $this->retryDelay()));
		//$client = new Client( array( 'handler' => $handlerStack ) );
		$this->client = new Client([
            'base_uri' => $this->endpoint,	
            'handler' => $handlerStack,
			'auth' => 'oauth',
			'stream'=>'true'

        ]);
	

       // $this->client->getEmitter()->attach($retry);
       // $this->client->getEmitter()->attach($oauth);
    }

    public function setRetries($num)
    {
        $this->retries = $num;
    }

    public function setLog($value)
    {
        $this->log = $value;
    }

    public function setLogger($value)
    {
        $this->logger = $value;
    }

    public function setFormatter($value)
    {
        $this->formater = $value;
    }

    public function getStatuses($param, $callback)
    {
		//guzzle 5 deprecated
		/*
        $response = $this->client->post('statuses/filter.json', [
            'body'   => $param
        ]);
		*/
		//echo $param['track'];
		
		$response = $this->client->request('POST', 'statuses/filter.json', [
				'form_params' => [
					'track' => $param['track']
				]
		]);
		
		

        $body = $response->getBody();

        while (!$body->eof()) {
            $line = Utils::readLine($body);
            $data = json_decode($line, true);
            if(is_null($data)) continue;
            call_user_func($callback, $data);
            if( ob_get_level() > 0 ) ob_flush();
            flush();
        }

    }
	
	function retryDecider() {
		   return function (
			  $retries,
			   $request,
			   $response = null,
			   $exception = null
		   ) {
			 // print_r($request);
			  // Limit the number of retries to 5
			  if ( $retries == 5 ) {
				 return false;
			  }

			  // Retry connection exceptions
			  if( $exception instanceof ConnectException ) {
				 return true;
			  }

			  if( $response ) {
				 // Retry on server errors
				 if( $response->getStatusCode() == 503 ) {
					return true;
				 }
			  }

			  return false;
		   };
		}
	function retryDelay() {
		return function( $numberOfRetries ) {
			return 1000 * $numberOfRetries;
		};	
	}
	
}