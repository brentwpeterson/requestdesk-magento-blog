<?php
/**
 * RequestDesk Blog - Inbound API key validation
 *
 * The single place inbound header-key auth is decided. It used to be two private
 * methods, one in ExternalBlog and one in DataExport, and they had drifted:
 * ExternalBlog accepted either header spelling while DataExport accepted only
 * `X-RequestDesk-Key`, so the same client key worked against /external/blog/* and
 * failed against /export/*. Two copies of an auth check is one copy too many.
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

namespace RequestDesk\Blog\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Webapi\Rest\Request;
use Psr\Log\LoggerInterface;

class ApiKeyValidator
{
    private const XML_PATH_API_KEY = 'requestdesk_blog/api/api_key';

    /**
     * Both spellings are accepted. RequestDesk has sent each of them at different
     * times and the header name is not worth a support ticket.
     */
    private const HEADERS = ['X-RequestDesk-Key', 'x-requestdesk-api-key'];

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Request $request
     * @param EncryptorInterface $encryptor
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Request $request,
        private readonly EncryptorInterface $encryptor,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Throw unless the request carries the configured key.
     *
     * @param string $context appears in the log line, so a failed attempt can be
     *                        traced to the surface it hit
     * @return void
     * @throws AuthorizationException
     */
    public function validate(string $context = 'API'): void
    {
        $encryptedKey = $this->scopeConfig->getValue(self::XML_PATH_API_KEY);

        if (empty($encryptedKey)) {
            throw new AuthorizationException(
                __('RequestDesk API key not configured in Magento admin')
            );
        }

        $configuredKey = (string) $this->encryptor->decrypt($encryptedKey);

        if ($configuredKey === '') {
            throw new AuthorizationException(
                __('RequestDesk API key not configured in Magento admin')
            );
        }

        $providedKey = '';
        foreach (self::HEADERS as $header) {
            $value = (string) $this->request->getHeader($header);
            if ($value !== '') {
                $providedKey = $value;
                break;
            }
        }

        // hash_equals, not !==. String comparison short-circuits on the first
        // differing byte, which leaks the key a character at a time to anyone
        // willing to time the responses.
        if ($providedKey === '' || !hash_equals($configuredKey, $providedKey)) {
            $this->logger->warning(sprintf('RequestDesk %s: Invalid API key attempt', $context));
            throw new AuthorizationException(
                __('Invalid RequestDesk API key')
            );
        }
    }
}
