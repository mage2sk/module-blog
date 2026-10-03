<?php
declare(strict_types=1);

namespace Panth\Blog\Test\Unit\Model\Comment;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\HTTP\Client\CurlFactory;
use Panth\Blog\Helper\Config;
use Panth\Blog\Model\Comment\Captcha;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CaptchaTest extends TestCase
{
    private array $values = [];

    private function captcha(): Captcha
    {
        $config = $this->createStub(Config::class);
        $config->method('getValue')->willReturnCallback(
            fn (string $path) => $this->values[$path] ?? null
        );
        $encryptor = $this->createStub(EncryptorInterface::class);
        $encryptor->method('hash')->willReturnCallback(
            static fn ($data) => hash_hmac('sha256', (string) $data, 'unit-test-key')
        );

        return new Captcha(
            $config,
            $encryptor,
            $this->createStub(CurlFactory::class),
            $this->createStub(LoggerInterface::class)
        );
    }

    private function solve(array $challenge): string
    {
        preg_match('/(\d+) plus (\d+)/', (string) $challenge['question'], $m);
        return (string) ((int) $m[1] + (int) $m[2]);
    }

    public function testDisabledByDefault(): void
    {
        $this->assertSame(Captcha::PROVIDER_NONE, $this->captcha()->getProvider());
    }

    public function testRemoteProviderWithoutKeysFallsBackToMath(): void
    {
        $this->values = ['comments/captcha_enabled' => '1', 'comments/captcha_provider' => 'hcaptcha'];
        $this->assertSame(Captcha::PROVIDER_MATH, $this->captcha()->getProvider());

        $this->values['comments/captcha_site_key'] = 'site';
        $this->values['comments/captcha_secret_key'] = 'secret';
        $this->assertSame(Captcha::PROVIDER_HCAPTCHA, $this->captcha()->getProvider());
    }

    public function testExplicitNoneProviderIsRespected(): void
    {
        $this->values = ['comments/captcha_enabled' => '1', 'comments/captcha_provider' => 'none'];
        $this->assertSame(Captcha::PROVIDER_NONE, $this->captcha()->getProvider());
    }

    public function testCorrectMathAnswerPasses(): void
    {
        $captcha = $this->captcha();
        $challenge = $captcha->createMathChallenge();
        $this->assertTrue($captcha->verifyMath($challenge['token'], $this->solve($challenge)));
    }

    public function testWrongMathAnswerFails(): void
    {
        $captcha = $this->captcha();
        $challenge = $captcha->createMathChallenge();
        $this->assertFalse($captcha->verifyMath($challenge['token'], (string) ((int) $this->solve($challenge) + 1)));
        $this->assertFalse($captcha->verifyMath($challenge['token'], ''));
    }

    public function testTamperedTokenFails(): void
    {
        $captcha = $this->captcha();
        $challenge = $captcha->createMathChallenge();
        [$first, $second, $nonce, $signature] = explode('-', $challenge['token']);
        $tampered = ((int) $first % 9) + 1;
        $this->assertFalse(
            $captcha->verifyMath($tampered . '-' . $second . '-' . $nonce . '-' . $signature, (string) ($tampered + (int) $second))
        );
        $this->assertFalse($captcha->verifyMath('garbage', '2'));
    }
}
