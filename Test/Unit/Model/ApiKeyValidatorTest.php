<?php
/**
 * Copyright © RequestDesk. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Webapi\Rest\Request;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RequestDesk\Blog\Model\ApiKeyValidator;

/**
 * Inbound API key validation.
 *
 * This is the only thing standing between the public internet and the endpoints
 * that create posts and export the catalog, so the tests are written against the
 * ways it can wrongly let someone in rather than against its happy path.
 *
 * It exists because the check used to be duplicated in ExternalBlog and
 * DataExport, and the copies had drifted: one accepted either header spelling,
 * the other accepted only X-RequestDesk-Key, so the same key worked against one
 * surface and 401'd against the other.
 */
class ApiKeyValidatorTest extends TestCase
{
    private const CONFIGURED = 'the-real-key';

    /** @var Request&MockObject */
    private Request $request;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    private function validatorWithStoredKey(?string $stored): ApiKeyValidator
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn($stored === null ? null : 'encrypted');

        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('decrypt')->willReturn((string) $stored);

        return new ApiKeyValidator($scopeConfig, $this->request, $encryptor, $this->logger);
    }

    /**
     * @param array<string, string> $headers
     */
    private function sendHeaders(array $headers): void
    {
        $this->request->method('getHeader')->willReturnCallback(
            static fn (string $name) => $headers[$name] ?? ''
        );
    }

    protected function setUp(): void
    {
        $this->request = $this->createMock(Request::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testCorrectKeyInTheCanonicalHeaderIsAccepted(): void
    {
        $this->sendHeaders(['X-RequestDesk-Key' => self::CONFIGURED]);

        $this->validatorWithStoredKey(self::CONFIGURED)->validate();

        $this->expectNotToPerformAssertions();
    }

    /**
     * The whole reason this class exists. RequestDesk has sent both spellings at
     * different times, and DataExport used to reject this one.
     */
    public function testCorrectKeyInTheLowercaseHeaderIsAccepted(): void
    {
        $this->sendHeaders(['x-requestdesk-api-key' => self::CONFIGURED]);

        $this->validatorWithStoredKey(self::CONFIGURED)->validate();

        $this->expectNotToPerformAssertions();
    }

    public function testWrongKeyIsRejected(): void
    {
        $this->sendHeaders(['X-RequestDesk-Key' => 'not-the-key']);

        $this->expectException(AuthorizationException::class);
        $this->validatorWithStoredKey(self::CONFIGURED)->validate();
    }

    /**
     * A request with no key at all must not be treated as a request whose key
     * happens to equal the empty string.
     */
    public function testMissingHeaderIsRejected(): void
    {
        $this->sendHeaders([]);

        $this->expectException(AuthorizationException::class);
        $this->validatorWithStoredKey(self::CONFIGURED)->validate();
    }

    public function testEmptyHeaderValueIsRejected(): void
    {
        $this->sendHeaders(['X-RequestDesk-Key' => '']);

        $this->expectException(AuthorizationException::class);
        $this->validatorWithStoredKey(self::CONFIGURED)->validate();
    }

    /**
     * The dangerous case: no key configured in admin. If an unconfigured store
     * compared '' to '' it would authorise every anonymous caller on a public
     * route. Both an absent config value and one that decrypts to empty must
     * refuse rather than match.
     */
    public function testUnconfiguredStoreRefusesEvenAnEmptyProvidedKey(): void
    {
        $this->sendHeaders(['X-RequestDesk-Key' => '']);

        $this->expectException(AuthorizationException::class);
        $this->validatorWithStoredKey(null)->validate();
    }

    public function testConfigValueThatDecryptsToEmptyIsRefused(): void
    {
        $this->sendHeaders(['X-RequestDesk-Key' => 'anything']);

        $this->expectException(AuthorizationException::class);
        $this->validatorWithStoredKey('')->validate();
    }

    /**
     * A prefix of the real key must not pass. This is the case a length-unaware
     * comparison gets wrong.
     */
    public function testKeyPrefixIsRejected(): void
    {
        $this->sendHeaders(['X-RequestDesk-Key' => substr(self::CONFIGURED, 0, 4)]);

        $this->expectException(AuthorizationException::class);
        $this->validatorWithStoredKey(self::CONFIGURED)->validate();
    }

    public function testFailedAttemptIsLoggedWithItsContext(): void
    {
        $this->sendHeaders(['X-RequestDesk-Key' => 'wrong']);
        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Data Export'));

        $this->expectException(AuthorizationException::class);
        $this->validatorWithStoredKey(self::CONFIGURED)->validate('Data Export');
    }
}
