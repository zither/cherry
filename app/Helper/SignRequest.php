<?php
namespace Cherry\Helper;

use Psr\Http\Message\RequestInterface;
use InvalidArgumentException;

class SignRequest
{
    /**
     * @var string
     */
    public $domain;

    /**
     * @var string
     */
    public $mainKeyId;

    /**
     * @var string
     */
    public $publicKey;

    /**
     * @var string
     */
    public $privateKey;

    /**
     * @param string $publicKey
     * @param string $privateKey
     * @return $this
     */
    public function withKey(string $publicKey, string $privateKey = null): self
    {
        $this->publicKey = $publicKey;
        $this->privateKey = $privateKey;
        return $this;
    }

    public function withDomain(string $domain): self
    {
        $this->domain = $domain;
        if (strpos($this->domain, 'https:') !== false) {
            $this->mainKeyId = "{$this->domain}#main-key";
        } else {
            $this->mainKeyId = "https://{$this->domain}#main-key";
        }
        return $this;
    }

    public function sign(RequestInterface $request, $ldSignature = false): RequestInterface
    {
        $uri = $request->getUri();
        $date = gmdate('D, d M Y H:i:s T', time());
        $request = $request->withHeader('Date', $date);

        // 从头取出
        $request->getBody()->rewind();
        $rawData = $request->getBody()->getContents();

        if ($ldSignature) {
            $data = json_decode($rawData, true);
            $data['signature'] = $this->createLdSignature($data);
            $data = json_encode($data, JSON_UNESCAPED_SLASHES);
        } else {
            $data = $rawData;
        }

        $digest = base64_encode(openssl_digest($data, 'sha256', true));
        $request = $request->withHeader('digest', "SHA-256=$digest");

        $signString = sprintf(
            "(request-target): %s %s\nhost: %s\ndate: %s\ndigest: %s\ncontent-type: %s",
            strtolower($request->getMethod()),
            $uri->getPath(),
            $uri->getHost(),
            $request->getHeaderLine('date'),
            $request->getHeaderLine('digest'),
            $request->getHeaderLine('content-type')
        );
        openssl_sign($signString, $signature, $this->getPrivateKey(), 'SHA256');

        $header = sprintf(
            'keyId="%s",algorithm="rsa-sha256",headers="(request-target) host date digest content-type",signature="%s"',
            $this->mainKeyId,
            base64_encode($signature)
        );

        return $request->withHeader('Signature', $header);
    }

    public function getHttpSignatureDataFromRequest(RequestInterface $request): array
    {
        $signature = $request->getHeaderLine('Signature');
        if (empty($signature)) {
            throw new InvalidArgumentException('Request not signed');
        }

        $fields = explode(',', $signature);
        $map = [];
        foreach ($fields as $field) {
            $tmp = explode('=', $field);
            if (count($tmp) < 2) {
                continue;
            }
            $map[$tmp[0]] = trim($tmp[1], '"');
        }
        if (!isset($map['headers'])) {
            throw new InvalidArgumentException('Invalid signature');
        }

        $requiredHeaders = explode(' ', str_replace('(request-target) ', '', $map['headers']));
        $arrayToSign = [];
        $rawDigest = '';
        foreach ($requiredHeaders as $k) {
            if (!$request->hasHeader($k)) {
                throw new InvalidArgumentException("$k header required");
            }
            $v = $request->getHeaderLine($k);
            if ($k == 'digest') {
                $rawDigest = str_replace('SHA-256=', '', $v);
            }
            $arrayToSign[$k] = $v;
        }

        if (empty($arrayToSign['digest'])) {
            throw new InvalidArgumentException("Digest header required");
        }
        $request->getBody()->rewind();
        $content = $request->getBody()->getContents();
        $digest = base64_encode(openssl_digest($content, 'sha256', true));
        if ($digest !== $rawDigest) {
            throw new InvalidArgumentException("Invalid digest");
        }

        $arr = [];
        foreach ($arrayToSign as $k => $v) {
            $arr[] = "$k: $v";
        }

        $uri = $request->getUri();
        $stringToVerify = sprintf(
            "(request-target): %s %s\n%s",
            strtolower($request->getMethod()),
            $uri->getPath(),
            implode("\n", $arr)
        );

        return [
            'key' => $map['keyId'],
            'signature' => $map['signature'],
            'algorithm' => $map['algorithm'] ?? 'rsa-sha256',
            'data' => $stringToVerify,
        ];
    }

    public function verifyHttpSignature(string $content, string $signature)
    {
        $result = openssl_verify($content, base64_decode($signature), $this->getPublicKey(), 'SHA256');
        return $result === 1;
    }

    public function verifyLdSignature(array $data)
    {
        $hash1 = $this->hash($this->getSignOptions($data['signature']));
        $hash2 = $this->hash($this->getSignData($data));
        $signatureValue = base64_decode($data['signature']['signatureValue']);
        $res = openssl_verify($hash1 . $hash2, $signatureValue, $this->getPublicKey(), 'sha256');
        return $res > 0 ? true : false;
    }

    public function createLdSignature(array $data)
    {
        $options = [
            'type' => 'RsaSignature2017',
            'creator' => $this->mainKeyId,
            'created' => Time::UTCTimeISO8601(),
        ];
        $hash1 = $this->hash($this->getSignOptions($options));
        $hash2 = $this->hash($this->getSignData($data));

        openssl_sign($hash1 . $hash2,$signatureValue, $this->getPrivateKey(), 'sha256');

        $options['signatureValue'] = base64_encode($signatureValue);
        $signed = array_merge([
            '@context' => [
               'https://www.w3.org/ns/activitystreams',
                'https://w3id.org/security/v1',
            ],
        ], $options);

        return $signed;
    }

    protected function getSignOptions($options)
    {
        $newOpts = [ '@context' => 'https://w3id.org/identity/v1' ];
        if ($options) {
            foreach($options as $k => $v) {
                if(!in_array($k,[ 'type','id','signatureValue' ])) {
                    $newOpts[$k] = $v;
                }
            }
        }
        return json_encode($newOpts,JSON_UNESCAPED_SLASHES);
    }

    protected function getSignData($data)
    {
        $newData = [];
        if ($data) {
            foreach($data as $k => $v) {
                if(!in_array($k,[ 'signature' ])) {
                    $newData[$k] = $v;
                }
            }
        }
        return json_encode($newData,JSON_UNESCAPED_SLASHES);
    }

    protected function hash($obj)
    {
        //return hash('sha256',$this->normalise($obj));
        return openssl_digest($this->normalise($obj), 'sha256');
    }

    protected function normalise($data)
    {
        if( is_string($data)) {
            $data = json_decode($data);
        }

        if(!is_object($data))
            return '';

        jsonld_set_document_loader('jsonld_document_loader');

        try {
            $d = jsonld_normalize($data, ['algorithm' => 'URDNA2015', 'format' => 'application/nquads']);
        } catch (\Exception $e) {
            // Don't log the exception - this can exhaust memory
            // logger('normalise error:' . print_r($e,true));
            //logger('normalise error: ' . print_r($data,true));
        }
        return $d;
    }

    protected function getPrivateKey()
    {
        if (empty($this->privateKey)) {
            throw new \RuntimeException('Private Key required!');
        }
        return $this->privateKey;
    }

    protected function getPublicKey()
    {
        if (empty($this->publicKey)) {
            throw new \RuntimeException('Public Key required!');
        }
        return $this->publicKey;
    }
}