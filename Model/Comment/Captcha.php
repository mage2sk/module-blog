<?php
declare(strict_types=1);

namespace Panth\Blog\Model\Comment;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\HTTP\Client\CurlFactory;
use Panth\Blog\Helper\Config;
use Psr\Log\LoggerInterface;

class Captcha
{
    public const PROVIDER_NONE = 'none';
    public const PROVIDER_MATH = 'math';
    public const PROVIDER_TURNSTILE = 'cloudflare_turnstile';
    public const PROVIDER_HCAPTCHA = 'hcaptcha';
    public const PROVIDER_RECAPTCHA_V3 = 'recaptcha_v3';

    public const FIELD_MATH_ANSWER = 'captcha_answer';
    public const FIELD_MATH_TOKEN = 'captcha_token';

    private const REMOTE_PROVIDERS = [
        self::PROVIDER_TURNSTILE => [
            'url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            'field' => 'cf-turnstile-response',
        ],
        self::PROVIDER_HCAPTCHA => [
            'url' => 'https://api.hcaptcha.com/siteverify',
            'field' => 'h-captcha-response',
        ],
        self::PROVIDER_RECAPTCHA_V3 => [
            'url' => 'https://www.google.com/recaptcha/api/siteverify',
            'field' => 'g-recaptcha-response',
        ],
    ];

    private const RECAPTCHA_MIN_SCORE = 0.5;

    public function __construct(
        private readonly Config $config,
        private readonly EncryptorInterface $encryptor,
        private readonly CurlFactory $curlFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getProvider(?int $storeId = null): string
    {
        if (!(bool) $this->config->getValue('comments/captcha_enabled', $storeId)) {
            return self::PROVIDER_NONE;
        }
        $provider = (string) $this->config->getValue('comments/captcha_provider', $storeId);
        if ($provider === self::PROVIDER_NONE) {
            return self::PROVIDER_NONE;
        }
        if (isset(self::REMOTE_PROVIDERS[$provider])) {
            if ($this->getSiteKey($storeId) !== '' && $this->getSecretKey($storeId) !== '') {
                return $provider;
            }
            return self::PROVIDER_MATH;
        }
        return self::PROVIDER_MATH;
    }

    public function getSiteKey(?int $storeId = null): string
    {
        return trim((string) $this->config->getValue('comments/captcha_site_key', $storeId));
    }

    public function getResponseField(string $provider): string
    {
        return self::REMOTE_PROVIDERS[$provider]['field'] ?? '';
    }

    public function createMathChallenge(): array
    {
        $first = random_int(1, 9);
        $second = random_int(1, 9);
        $nonce = bin2hex(random_bytes(8));
        $payload = $first . '-' . $second . '-' . $nonce;

        return [
            'question' => (string) __('What is %1 plus %2?', $first, $second),
            'token' => $payload . '-' . $this->sign($payload),
        ];
    }

    public function verify(RequestInterface $request, string $clientIp, ?int $storeId = null): bool
    {
        $provider = $this->getProvider($storeId);
        if ($provider === self::PROVIDER_NONE) {
            return true;
        }
        if ($provider === self::PROVIDER_MATH) {
            return $this->verifyMath(
                (string) $request->getParam(self::FIELD_MATH_TOKEN, ''),
                (string) $request->getParam(self::FIELD_MATH_ANSWER, '')
            );
        }
        $response = trim((string) $request->getParam($this->getResponseField($provider), ''));
        return $this->verifyRemote($provider, $response, $clientIp, $storeId);
    }

    public function verifyMath(string $token, string $answer): bool
    {
        $parts = explode('-', trim($token));
        if (count($parts) !== 4) {
            return false;
        }
        [$first, $second, $nonce, $signature] = $parts;
        if (!ctype_digit($first) || !ctype_digit($second) || $nonce === '') {
            return false;
        }
        $expected = $this->sign($first . '-' . $second . '-' . $nonce);
        if (!hash_equals($expected, $signature)) {
            return false;
        }
        $answer = trim($answer);
        return ctype_digit($answer) && (int) $answer === (int) $first + (int) $second;
    }

    private function verifyRemote(string $provider, string $response, string $clientIp, ?int $storeId): bool
    {
        if ($response === '') {
            return false;
        }
        $params = [
            'secret' => $this->getSecretKey($storeId),
            'response' => $response,
        ];
        if ($clientIp !== '') {
            $params['remoteip'] = $clientIp;
        }
        try {
            $curl = $this->curlFactory->create();
            $curl->setTimeout(10);
            $curl->post(self::REMOTE_PROVIDERS[$provider]['url'], $params);
            $data = json_decode((string) $curl->getBody(), true);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_Blog] CAPTCHA verification failed: ' . $e->getMessage());
            return false;
        }
        if (!is_array($data) || empty($data['success'])) {
            return false;
        }
        if ($provider === self::PROVIDER_RECAPTCHA_V3) {
            return (float) ($data['score'] ?? 0) >= self::RECAPTCHA_MIN_SCORE;
        }
        return true;
    }

    private function getSecretKey(?int $storeId = null): string
    {
        return trim((string) $this->config->getValue('comments/captcha_secret_key', $storeId));
    }

    private function sign(string $payload): string
    {
        return substr($this->encryptor->hash('panth_blog_captcha|' . $payload), 0, 32);
    }
}
