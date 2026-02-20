<?php

/**
 * Copyright 2026 Mehrnet
 * SPDX-License-Identifier: Apache-2.0
 */
class Registrar_Adapter_Porkbun extends Registrar_AdapterAbstract
{
    public const DEFAULT_API_URL = 'https://api.porkbun.com/api/json/v3';

    public $config = [
        'apikey' => null,
        'secretapikey' => null,
        'api_url' => self::DEFAULT_API_URL,
    ];

    public function __construct($options)
    {
        if (empty($options['apikey'])) {
            throw new Registrar_Exception(
                'The ":domain_registrar" domain registrar is not fully configured. Please configure the :missing',
                [':domain_registrar' => 'Porkbun', ':missing' => 'API Key'],
                3001
            );
        }

        if (empty($options['secretapikey'])) {
            throw new Registrar_Exception(
                'The ":domain_registrar" domain registrar is not fully configured. Please configure the :missing',
                [':domain_registrar' => 'Porkbun', ':missing' => 'Secret API Key'],
                3001
            );
        }

        $this->config['apikey'] = trim((string) $options['apikey']);
        $this->config['secretapikey'] = trim((string) $options['secretapikey']);

        if (!empty($options['api_url'])) {
            $this->config['api_url'] = rtrim((string) $options['api_url'], '/');
        }
    }

    public static function getConfig(): array
    {
        return [
            'label' => 'Manage domains via the Porkbun API v3.',
            'form' => [
                'apikey' => [
                    'password', [
                        'label' => 'API Key',
                        'required' => true,
                    ],
                ],
                'secretapikey' => [
                    'password', [
                        'label' => 'Secret API Key',
                        'required' => true,
                    ],
                ],
                'api_url' => [
                    'text', [
                        'label' => 'API Base URL',
                        'description' => 'Optional. Defaults to https://api.porkbun.com/api/json/v3',
                        'required' => false,
                    ],
                ],
            ],
        ];
    }

    public function isDomainAvailable(Registrar_Domain $domain): bool
    {
        $fqdn = $this->normalizeDomain($domain);
        $result = $this->post('domain/checkDomain/' . rawurlencode($fqdn));

        $response = $result['response'] ?? [];
        if (($response['premium'] ?? 'no') === 'yes') {
            throw new Registrar_Exception('Premium domains are not supported by this adapter.');
        }

        return ($response['avail'] ?? 'no') === 'yes';
    }

    public function isDomaincanBeTransferred(Registrar_Domain $domain): bool
    {
        throw $this->unsupportedOperation('transfer availability checks');
    }

    public function modifyNs(Registrar_Domain $domain): bool
    {
        $fqdn = $this->normalizeDomain($domain);
        $nameservers = array_values(array_filter([
            $domain->getNs1(),
            $domain->getNs2(),
            $domain->getNs3(),
            $domain->getNs4(),
        ], static fn ($ns): bool => is_string($ns) && trim($ns) !== ''));

        if (count($nameservers) < 2) {
            throw new Registrar_Exception('At least two name servers are required.');
        }

        $this->post('domain/updateNs/' . rawurlencode($fqdn), ['ns' => $nameservers]);

        return true;
    }

    public function modifyContact(Registrar_Domain $domain): bool
    {
        throw $this->unsupportedOperation('contact updates');
    }

    public function transferDomain(Registrar_Domain $domain): bool
    {
        throw $this->unsupportedOperation('domain transfers');
    }

    public function getDomainDetails(Registrar_Domain $domain): Registrar_Domain
    {
        $fqdn = $this->normalizeDomain($domain);
        $accountDomain = $this->findDomainInAccount($fqdn);

        if ($accountDomain === null) {
            throw new Registrar_Exception('Domain ":domain" was not found in the Porkbun account.', [':domain' => $fqdn]);
        }

        $createdAt = $this->parseTimestamp($accountDomain['createDate'] ?? null);
        if ($createdAt !== null) {
            $domain->setRegistrationTime($createdAt);
        }

        $expiresAt = $this->parseTimestamp($accountDomain['expireDate'] ?? null);
        if ($expiresAt !== null) {
            $domain->setExpirationTime($expiresAt);
        }

        $domain->setLocked(($accountDomain['securityLock'] ?? '0') === '1');
        $domain->setPrivacyEnabled(($accountDomain['whoisPrivacy'] ?? '0') === '1');

        $nameServerResult = $this->post('domain/getNs/' . rawurlencode($fqdn));
        $nameServers = $nameServerResult['ns'] ?? [];

        $domain->setNs1($nameServers[0] ?? null);
        $domain->setNs2($nameServers[1] ?? null);
        $domain->setNs3($nameServers[2] ?? null);
        $domain->setNs4($nameServers[3] ?? null);

        return $domain;
    }

    public function getEpp(Registrar_Domain $domain): string
    {
        throw $this->unsupportedOperation('EPP/auth code retrieval');
    }

    public function registerDomain(Registrar_Domain $domain): bool
    {
        $fqdn = $this->normalizeDomain($domain);
        $checkResult = $this->post('domain/checkDomain/' . rawurlencode($fqdn));
        $checkResponse = $checkResult['response'] ?? [];

        if (($checkResponse['avail'] ?? 'no') !== 'yes') {
            throw new Registrar_Exception('Domain ":domain" is not available.', [':domain' => $fqdn]);
        }

        if (($checkResponse['premium'] ?? 'no') === 'yes') {
            throw new Registrar_Exception('Premium domains are not supported by this adapter.');
        }

        $minDuration = (int) ($checkResponse['minDuration'] ?? 1);
        $requestedYears = (int) ($domain->getRegistrationPeriod() ?: $minDuration);

        if ($requestedYears !== $minDuration) {
            throw new Registrar_Exception(
                'Porkbun API registers this TLD for :years year(s) minimum; requested :requested year(s).',
                [':years' => $minDuration, ':requested' => $requestedYears]
            );
        }

        if (!isset($checkResponse['price'])) {
            throw new Registrar_Exception('Porkbun availability response did not include a price.');
        }

        $costPennies = $this->calculateCostInPennies((string) $checkResponse['price'], $minDuration);

        $this->post('domain/create/' . rawurlencode($fqdn), [
            'cost' => $costPennies,
            'agreeToTerms' => 'yes',
        ]);

        return true;
    }

    public function renewDomain(Registrar_Domain $domain): bool
    {
        throw $this->unsupportedOperation('manual renewals');
    }

    public function deleteDomain(Registrar_Domain $domain): bool
    {
        throw $this->unsupportedOperation('domain deletions');
    }

    public function enablePrivacyProtection(Registrar_Domain $domain): bool
    {
        throw $this->unsupportedOperation('privacy protection updates');
    }

    public function disablePrivacyProtection(Registrar_Domain $domain): bool
    {
        throw $this->unsupportedOperation('privacy protection updates');
    }

    public function lock(Registrar_Domain $domain): bool
    {
        throw $this->unsupportedOperation('domain lock updates');
    }

    public function unlock(Registrar_Domain $domain): bool
    {
        throw $this->unsupportedOperation('domain lock updates');
    }

    protected function post(string $endpoint, array $payload = []): array
    {
        $requestBody = array_merge([
            'secretapikey' => $this->config['secretapikey'],
            'apikey' => $this->config['apikey'],
        ], $payload);

        $url = rtrim((string) $this->config['api_url'], '/') . '/' . ltrim($endpoint, '/');

        $logBody = $requestBody;
        $logBody['apikey'] = '***';
        $logBody['secretapikey'] = '***';
        $this->getLog()->debug('Porkbun API request: ' . $url . ' payload=' . json_encode($logBody));

        [$statusCode, $rawBody] = $this->sendRequest($url, $requestBody, true);

        // Fallback for installations where JSON POST bodies are rejected with HTTP 400.
        if ($statusCode === 400) {
            [$statusCode, $rawBody] = $this->sendRequest($url, $requestBody, false);
        }

        $decoded = json_decode($rawBody, true);
        $apiMessage = is_array($decoded) ? (string) ($decoded['message'] ?? '') : '';

        if ($statusCode !== 200) {
            throw new Registrar_Exception(
                'Porkbun API HTTP :code on :endpoint: :message',
                [
                    ':code' => $statusCode,
                    ':endpoint' => $endpoint,
                    ':message' => $apiMessage !== '' ? $apiMessage : 'Unknown HTTP error',
                ]
            );
        }

        if (!is_array($decoded)) {
            throw new Registrar_Exception('Porkbun API returned an invalid JSON response.');
        }

        if (strtoupper((string) ($decoded['status'] ?? '')) !== 'SUCCESS') {
            throw new Registrar_Exception(
                'Porkbun API error: :error',
                [':error' => (string) ($decoded['message'] ?? 'Unknown error')]
            );
        }

        return $decoded;
    }

    protected function sendRequest(string $url, array $requestBody, bool $asJson): array
    {
        $options = [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'timeout' => 60,
        ];

        if ($asJson) {
            $options['json'] = $requestBody;
        } else {
            $options['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
            $options['body'] = http_build_query($requestBody, '', '&');
        }

        try {
            $response = $this->getHttpClient()->request('POST', $url, $options);
        } catch (Throwable $e) {
            throw new Registrar_Exception('Porkbun request failed: :error', [':error' => $e->getMessage()]);
        }

        return [$response->getStatusCode(), $response->getContent(false)];
    }

    protected function normalizeDomain(Registrar_Domain $domain): string
    {
        return strtolower(trim((string) $domain->getName()));
    }

    protected function calculateCostInPennies(string $price, int $years): int
    {
        return (int) round(((float) $price) * 100 * $years, 0, PHP_ROUND_HALF_UP);
    }

    protected function parseTimestamp(?string $timestamp): ?int
    {
        if ($timestamp === null || trim($timestamp) === '') {
            return null;
        }

        $unix = strtotime($timestamp . ' UTC');

        if ($unix === false) {
            return null;
        }

        return $unix;
    }

    protected function findDomainInAccount(string $fqdn): ?array
    {
        $start = 0;

        while (true) {
            $result = $this->post('domain/listAll', ['start' => (string) $start]);
            $domains = $result['domains'] ?? [];

            if (!is_array($domains) || empty($domains)) {
                return null;
            }

            foreach ($domains as $domainData) {
                if (!is_array($domainData)) {
                    continue;
                }

                if (strcasecmp((string) ($domainData['domain'] ?? ''), $fqdn) === 0) {
                    return $domainData;
                }
            }

            if (count($domains) < 1000) {
                return null;
            }

            $start += 1000;
            if ($start > 50000) {
                throw new Registrar_Exception('Unable to locate domain in Porkbun account after scanning many records.');
            }
        }
    }

    protected function unsupportedOperation(string $operation): Registrar_Exception
    {
        return new Registrar_Exception(
            'Porkbun API v3 does not expose :operation in the public documentation.',
            [':operation' => $operation]
        );
    }
}
