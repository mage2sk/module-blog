<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model\Comment;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Comment\Captcha;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CaptchaVerifyTest extends TestCase
{
    private array $values = [];
    private array $posted = [];

    private function captcha(?string $body = null, ?\Throwable $error = null, ?LoggerInterface $logger = null): Captcha
    {
        $config = $this->createStub(Config::class);
        $config->method('getValue')->willReturnCallback(fn (string $path) => $this->values[$path] ?? null);
        $encryptor = $this->createStub(EncryptorInterface::class);
        $encryptor->method('hash')->willReturnCallback(static fn ($data) => hash('sha256', (string) $data));

        $curl = $this->createStub(Curl::class);
        $curl->method('post')->willReturnCallback(function ($url, $params) use ($error) {
            if ($error !== null) {
                throw $error;
            }
            $this->posted = [$url, $params];
        });
        $curl->method('getBody')->willReturn((string) $body);
        $factory = $this->createStub(CurlFactory::class);
        $factory->method('create')->willReturn($curl);

        return new Captcha($config, $encryptor, $factory, $logger ?? $this->createStub(LoggerInterface::class));
    }

    private function request(array $params): Http
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnCallback(static fn ($k, $d = null) => $params[$k] ?? $d);
        return $request;
    }

    private function remote(string $provider): void
    {
        $this->values = [
            'comments/captcha_enabled' => '1',
            'comments/captcha_provider' => $provider,
            'comments/captcha_site_key' => ' site ',
            'comments/captcha_secret_key' => ' secret ',
        ];
    }

    public function testDisabledCaptchaAlwaysPasses(): void
    {
        $this->assertTrue($this->captcha()->verify($this->request([]), '1.1.1.1'));
    }

    public function testMathCaptchaVerifiesSubmittedToken(): void
    {
        $this->values = ['comments/captcha_enabled' => '1', 'comments/captcha_provider' => 'math'];
        $captcha = $this->captcha();
        $challenge = $captcha->createMathChallenge();
        preg_match('/(\d+) plus (\d+)/', (string) $challenge['question'], $m);
        $answer = (string) ((int) $m[1] + (int) $m[2]);

        $this->assertTrue($captcha->verify($this->request(['captcha_token' => $challenge['token'], 'captcha_answer' => ' ' . $answer . ' ']), ''));
        $this->assertFalse($captcha->verify($this->request(['captcha_token' => $challenge['token'], 'captcha_answer' => 'abc']), ''));
        $this->assertFalse($captcha->verify($this->request([]), ''));
    }

    public static function providers(): array
    {
        return [
            ['cloudflare_turnstile', 'cf-turnstile-response', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'],
            ['hcaptcha', 'h-captcha-response', 'https://api.hcaptcha.com/siteverify'],
            ['recaptcha_v3', 'g-recaptcha-response', 'https://www.google.com/recaptcha/api/siteverify'],
        ];
    }

    #[DataProvider('providers')]
    public function testRemoteProviderPostsSecretAndResponse(string $provider, string $field, string $url): void
    {
        $this->remote($provider);
        $captcha = $this->captcha('{"success":true,"score":0.9}');

        $this->assertSame($provider, $captcha->getProvider());
        $this->assertSame('site', $captcha->getSiteKey());
        $this->assertSame($field, $captcha->getResponseField($provider));
        $this->assertTrue($captcha->verify($this->request([$field => ' tok ']), '9.9.9.9'));
        $this->assertSame([$url, ['secret' => 'secret', 'response' => 'tok', 'remoteip' => '9.9.9.9']], $this->posted);
    }

    public function testRemoteProviderRejectsMissingResponseOrFailure(): void
    {
        $this->remote('hcaptcha');
        $this->assertFalse($this->captcha('{"success":true}')->verify($this->request([]), ''));
        $this->assertFalse($this->captcha('{"success":false}')->verify($this->request(['h-captcha-response' => 'x']), ''));
        $this->assertFalse($this->captcha('not json')->verify($this->request(['h-captcha-response' => 'x']), ''));
    }

    public function testRemoteRequestWithoutIpOmitsRemoteIp(): void
    {
        $this->remote('cloudflare_turnstile');
        $this->captcha('{"success":true}')->verify($this->request(['cf-turnstile-response' => 'x']), '');
        $this->assertArrayNotHasKey('remoteip', $this->posted[1]);
    }

    public function testRecaptchaScoreThreshold(): void
    {
        $this->remote('recaptcha_v3');
        $request = $this->request(['g-recaptcha-response' => 'x']);
        $this->assertFalse($this->captcha('{"success":true,"score":0.3}')->verify($request, ''));
        $this->assertTrue($this->captcha('{"success":true,"score":0.5}')->verify($request, ''));
        $this->assertFalse($this->captcha('{"success":true}')->verify($request, ''));
    }

    public function testTransportErrorsAreLoggedAndFail(): void
    {
        $this->remote('hcaptcha');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($this->stringContains('timeout'));
        $this->assertFalse($this->captcha(null, new \RuntimeException('timeout'), $logger)->verify($this->request(['h-captcha-response' => 'x']), ''));
    }

    public function testUnknownProviderFallsBackToMathAndFieldIsEmpty(): void
    {
        $this->values = ['comments/captcha_enabled' => '1', 'comments/captcha_provider' => 'mystery'];
        $captcha = $this->captcha();
        $this->assertSame('math', $captcha->getProvider());
        $this->assertSame('', $captcha->getResponseField('mystery'));
    }
}
