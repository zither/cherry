<?php
namespace Cherry\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use InvalidArgumentException;
use Exception;
use Cherry\Helper\SignRequest;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;

class DevController extends BaseController
{
    public function objects(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $url = $this->getQueryParam($request, 'url');
        if (empty($url)) {
            throw new InvalidArgumentException('Invalid url');
        }

        $guzzleErrorMessage = $curlErrorMessage = $errorMessage = '';
        $targetObject = null;

        try {
            $helper = $this->container->get(SignRequest::class);
            try {
                $client = new Client();
                $fetchingRequest = new Request('GET', $url, ['Accept' => 'application/activity+json']);
                $fetchingRequest = $helper->sign($fetchingRequest);
                $fetchingResponse = $client->send($fetchingRequest);
                $body = $fetchingResponse->getBody();
                $body->rewind();
                $json = $body->getContents();
                $targetObject = json_decode($json, true);
            } catch (GuzzleException $e) {
                $guzzleErrorMessage = $e->getMessage();
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/activity+json']);
                $res = curl_exec($ch);
                $curlErrorMessage = curl_error($ch);
                curl_close($ch);
                if (empty($curlErrorMessage)) {
                    $targetObject = json_decode($res, true);
                }
            }
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
        }

        if (!empty($targetObject)) {
            $content = sprintf('<pre>%s</pre>', print_r($targetObject, true));
        } else {
            $content = sprintf('<p>GuzzleErrorMessage:%s</p><p>CurlErrorMessage:%s</p><p>ErrorMessage:%s</p>',
                $guzzleErrorMessage, $curlErrorMessage, $errorMessage
            );
        }

        $response->getBody()->write($content);
        return $response;
    }
}