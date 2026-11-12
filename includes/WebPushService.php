<?php
/**
 * WebPushService — Pure PHP Web Push implementation
 * No Composer dependencies. Uses raw cURL + openssl for VAPID.
 */
class WebPushService
{
    private PDO $pdo;
    private string $publicKey;
    private string $privateKey;
    private string $subject;
    private static string $keyFile = '';
    private static string $opensslConf = '';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->ensureTable();

        // Auto-detect OpenSSL config (critical for XAMPP/Windows)
        self::$opensslConf = self::findOpensslConf();

        // Load or generate VAPID keys
        self::$keyFile = dirname(__DIR__) . '/config/vapid_keys.json';
        $keys = $this->loadOrGenerateKeys();
        $this->publicKey  = $keys['public'];
        $this->privateKey = $keys['private'];
        $this->subject    = defined('SITE_URL') ? SITE_URL : (defined('SITE_DOMAIN') ? 'https://' . SITE_DOMAIN : 'https://localhost');
    }

    /**
     * Locate the OpenSSL config file — required on Windows/XAMPP
     * where openssl_pkey_new() fails without it.
     */
    private static function findOpensslConf(): string
    {
        // Check environment variable first
        $env = getenv('OPENSSL_CONF');
        if ($env && file_exists($env)) return $env;

        // Common XAMPP locations
        $candidates = [
            'C:/xampp/php/extras/openssl/openssl.cnf',
            'C:/xampp/php/extras/ssl/openssl.cnf',
            'C:/xampp/apache/conf/openssl.cnf',
            // Linux fallbacks
            '/etc/ssl/openssl.cnf',
            '/usr/local/ssl/openssl.cnf',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) return $path;
        }

        return ''; // Empty = let OpenSSL use its default
    }

    /**
     * Build the options array for openssl_pkey_new() with the config path
     */
    private static function ecKeyOptions(): array
    {
        $opts = [
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ];
        if (!empty(self::$opensslConf)) {
            $opts['config'] = self::$opensslConf;
        }
        return $opts;
    }

    /* ─── DB Table ─────────────────────────────────────────────── */
    private function ensureTable(): void
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            endpoint TEXT NOT NULL,
            p256dh VARCHAR(500) NOT NULL,
            auth VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_push_user (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /* ─── VAPID Key Management ─────────────────────────────────── */
    private function loadOrGenerateKeys(): array
    {
        if (file_exists(self::$keyFile)) {
            $data = json_decode(file_get_contents(self::$keyFile), true);
            // Must have public, private, AND pem — regenerate if pem missing
            if (!empty($data['public']) && !empty($data['private']) && !empty($data['pem'])) {
                return $data;
            }
        }

        // Generate new ECDSA P-256 keys
        $key = openssl_pkey_new(self::ecKeyOptions());

        if (!$key) {
            error_log('WebPush: Failed to generate VAPID keys — ' . openssl_error_string());
            return ['public' => '', 'private' => '', 'pem' => ''];
        }

        $details = openssl_pkey_get_details($key);
        $x = $details['ec']['x'];
        $y = $details['ec']['y'];
        $d = $details['ec']['d'];

        $publicKey  = rtrim(strtr(base64_encode(chr(4) . $x . $y), '+/', '-_'), '=');
        $privateKey = rtrim(strtr(base64_encode($d), '+/', '-_'), '=');

        // Export the native PEM — this is the only reliable way to get a
        // PEM that openssl_sign() will accept later for VAPID JWT signing
        $pem = '';
        openssl_pkey_export($key, $pem, null, self::ecKeyOptions());

        $data = ['public' => $publicKey, 'private' => $privateKey, 'pem' => $pem];
        $dir = dirname(self::$keyFile);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        file_put_contents(self::$keyFile, json_encode($data, JSON_PRETTY_PRINT));

        return $data;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    /* ─── Subscription CRUD ────────────────────────────────────── */
    public function saveSubscription(int $userId, string $endpoint, string $p256dh, string $auth): bool
    {
        // Upsert: delete existing with same endpoint, then insert fresh
        $del = $this->pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?");
        $del->execute([$endpoint]);

        $ins = $this->pdo->prepare("INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth) VALUES (?, ?, ?, ?)");
        return $ins->execute([$userId, $endpoint, $p256dh, $auth]);
    }

    public function removeSubscription(string $endpoint): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?");
        return $stmt->execute([$endpoint]);
    }

    public function getUserSubscriptions(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM push_subscriptions WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* ─── Send Push to a User — debug version ────────────────── */
    public function sendToUserDebug(int $userId, string $title, string $body, ?string $url = null): array
    {
        $debug = ['steps' => []];
        
        if (empty($this->publicKey) || empty($this->privateKey)) {
            $debug['error'] = 'VAPID keys are empty';
            return $debug;
        }
        $debug['steps'][] = '✅ VAPID keys loaded';
        $debug['steps'][] = '   OpenSSL conf: ' . (self::$opensslConf ?: '(default)');

        $subs = $this->getUserSubscriptions($userId);
        if (empty($subs)) {
            $debug['error'] = 'No push subscriptions found for user ' . $userId;
            return $debug;
        }
        $debug['steps'][] = '✅ Found ' . count($subs) . ' subscription(s)';

        $payload = json_encode([
            'title' => mb_substr($title, 0, 120),
            'body'  => mb_substr(strip_tags($body), 0, 200),
            'url'   => $url ?: (defined('SITE_URL') ? SITE_URL : '/'),
            'icon'  => (defined('SITE_URL') ? SITE_URL : '') . '/assets/img/favicon.png',
        ], JSON_UNESCAPED_UNICODE);
        $debug['payload'] = $payload;

        foreach ($subs as $i => $sub) {
            try {
                $result = $this->sendPush($sub['endpoint'], $sub['p256dh'], $sub['auth'], $payload);
                $debug['results'][] = [
                    'endpoint' => substr($sub['endpoint'], 0, 60) . '...',
                    'full_endpoint' => $sub['endpoint'],
                    'http_code' => $result['http_code'],
                    'response_body' => $result['response_body'],
                    'curl_error' => $result['curl_error'],
                    'steps' => $result['steps'] ?? [],
                ];
            } catch (\Throwable $e) {
                $debug['results'][] = [
                    'endpoint' => substr($sub['endpoint'], 0, 60) . '...',
                    'exception' => $e->getMessage(),
                ];
            }
        }

        return $debug;
    }

    /* ─── Send Push to a User (production — fire and forget) ──── */
    public function sendToUser(int $userId, string $title, string $body, ?string $url = null, ?string $icon = null): void
    {
        try {
            $result = $this->sendToUserDebug($userId, $title, $body, $url);
            // Log errors for monitoring
            if (!empty($result['error'])) {
                error_log('WebPush: ' . $result['error']);
            }
            if (!empty($result['results'])) {
                foreach ($result['results'] as $r) {
                    if (!empty($r['exception'])) {
                        error_log('WebPush exception: ' . $r['exception']);
                    }
                    if (isset($r['http_code']) && ($r['http_code'] === 410 || $r['http_code'] === 404)) {
                        $this->removeSubscription($r['full_endpoint'] ?? '');
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('WebPush send error: ' . $e->getMessage());
        }
    }

    /* ─── Low-Level Push Sender ────────────────────────────────── */
    private function sendPush(string $endpoint, string $p256dh, string $auth, string $payload): array
    {
        $result = ['http_code' => 0, 'response_body' => '', 'curl_error' => '', 'steps' => []];

        // 1. Create local ECDH key pair for content encryption
        $localKey = openssl_pkey_new(self::ecKeyOptions());
        if (!$localKey) {
            $err = openssl_error_string();
            $result['steps'][] = '❌ Local EC key failed: ' . $err;
            throw new \RuntimeException('Failed to create local EC key: ' . $err);
        }
        $result['steps'][] = '✅ Local EC key created';

        $localDetails = openssl_pkey_get_details($localKey);
        $localPub = chr(4) . $localDetails['ec']['x'] . $localDetails['ec']['y'];

        // 2. Derive shared secret via ECDH
        $userPubRaw = self::base64UrlDecode($p256dh);
        $authRaw    = self::base64UrlDecode($auth);
        $sharedSecret = $this->ecdhSharedSecret($localKey, $userPubRaw);
        $result['steps'][] = '✅ ECDH shared secret derived (' . strlen($sharedSecret) . ' bytes)';

        // 3. HKDF derivation for content encryption
        $salt = random_bytes(16);

        // PRK = HKDF-Extract(auth, sharedSecret)
        $ikm = $sharedSecret;
        $prk = hash_hmac('sha256', $ikm, $authRaw, true);

        // Build info strings per RFC 8291
        $keyInfo    = "WebPush: info\x00" . $userPubRaw . $localPub;
        $ikmFinal   = self::hkdfExpand($prk, $keyInfo, 32);

        $prkFinal   = hash_hmac('sha256', $ikmFinal, $salt, true);

        $cekInfo    = "Content-Encoding: aes128gcm\x00";
        $cek        = self::hkdfExpand($prkFinal, $cekInfo, 16);

        $nonceInfo  = "Content-Encoding: nonce\x00";
        $nonce      = self::hkdfExpand($prkFinal, $nonceInfo, 12);
        $result['steps'][] = '✅ HKDF keys derived (CEK: ' . strlen($cek) . 'B, nonce: ' . strlen($nonce) . 'B)';

        // 4. Encrypt payload with AES-128-GCM
        $paddedPayload = $payload . "\x02"; // delimiter byte
        $tag = '';
        $encrypted = openssl_encrypt($paddedPayload, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($encrypted === false) {
            $result['steps'][] = '❌ AES encryption failed: ' . openssl_error_string();
            throw new \RuntimeException('AES encryption failed');
        }
        $result['steps'][] = '✅ Payload encrypted (' . strlen($encrypted) . 'B + ' . strlen($tag) . 'B tag)';

        // 5. Build the encrypted body per aes128gcm encoding
        $recordSize = pack('N', 4096);
        $keyIdLen   = chr(strlen($localPub));
        $body       = $salt . $recordSize . $keyIdLen . $localPub . $encrypted . $tag;
        $result['steps'][] = '✅ Body assembled (' . strlen($body) . ' bytes total)';

        // 6. Create VAPID Authorization header
        $vapidHeaders = $this->createVapidHeaders($endpoint);
        $result['steps'][] = '✅ VAPID JWT created';
        $result['vapid_header'] = substr($vapidHeaders['authorization'], 0, 80) . '...';

        // 7. Send via cURL
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                'Content-Length: ' . strlen($body),
                'TTL: 2419200',
                'Urgency: normal',
                $vapidHeaders['authorization'],
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $responseBody = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        $result['http_code'] = $httpCode;
        $result['response_body'] = $responseBody ?: '';
        $result['curl_error'] = $curlError;
        $result['curl_errno'] = $curlErrno;
        $result['steps'][] = "HTTP $httpCode" . ($curlError ? " | cURL error: $curlError" : '') . ($responseBody ? " | Body: $responseBody" : '');

        return $result;
    }

    /* ─── VAPID JWT ────────────────────────────────────────────── */
    private function createVapidHeaders(string $endpoint): array
    {
        $parsed = parse_url($endpoint);
        $audience = $parsed['scheme'] . '://' . $parsed['host'];

        $header = self::base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));

        $payload = self::base64UrlEncode(json_encode([
            'aud' => $audience,
            'exp' => time() + 43200, // 12 hours
            'sub' => $this->subject,
        ]));

        $signingInput = "$header.$payload";

        // Sign with ECDSA P-256 using the stored native PEM
        $pem = $this->getPrivateKeyPem();
        $pKey = openssl_pkey_get_private($pem);
        if (!$pKey) {
            throw new \RuntimeException('VAPID: Cannot load private key PEM — ' . openssl_error_string());
        }
        $sig = '';
        if (!openssl_sign($signingInput, $sig, $pKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('VAPID: openssl_sign failed — ' . openssl_error_string());
        }

        // Convert DER signature to raw r||s (64 bytes)
        $rawSig = self::derToRaw($sig);
        $jwt = $signingInput . '.' . self::base64UrlEncode($rawSig);

        return [
            'authorization' => 'Authorization: vapid t=' . $jwt . ', k=' . $this->publicKey,
        ];
    }

    private function getPrivateKeyPem(): string
    {
        // Use the native PEM stored during key generation
        $keyData = json_decode(file_get_contents(self::$keyFile), true);
        return $keyData['pem'] ?? '';
    }

    /* ─── ECDH Shared Secret ───────────────────────────────────── */
    private function ecdhSharedSecret($localPrivKey, string $remotePubRaw): string
    {
        // Build a temporary PEM for the remote public key
        $ecOid = hex2bin('06082a8648ce3d030107');
        $algorithmId = hex2bin('06072a8648ce3d0201');
        $algorithmSeq = "\x30" . self::asn1Length(strlen($algorithmId) + strlen($ecOid)) . $algorithmId . $ecOid;
        $bitString = "\x03" . self::asn1Length(strlen($remotePubRaw) + 1) . "\x00" . $remotePubRaw;
        $pubKeyDer = "\x30" . self::asn1Length(strlen($algorithmSeq) + strlen($bitString)) . $algorithmSeq . $bitString;

        $remotePem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($pubKeyDer), 64, "\n")
            . "-----END PUBLIC KEY-----\n";

        $remotePubKey = openssl_pkey_get_public($remotePem);
        if (!$remotePubKey) {
            throw new \RuntimeException('Failed to parse remote public key: ' . openssl_error_string());
        }

        $shared = openssl_pkey_derive($remotePubKey, $localPrivKey);
        if ($shared === false || empty($shared)) {
            throw new \RuntimeException('ECDH derive failed: ' . openssl_error_string());
        }

        return $shared;
    }

    /* ─── Helpers ──────────────────────────────────────────────── */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }

    private static function hkdfExpand(string $prk, string $info, int $length): string
    {
        $t = '';
        $lastBlock = '';
        $counter = 1;
        while (strlen($t) < $length) {
            $lastBlock = hash_hmac('sha256', $lastBlock . $info . chr($counter), $prk, true);
            $t .= $lastBlock;
            $counter++;
        }
        return substr($t, 0, $length);
    }

    private static function derToRaw(string $der): string
    {
        // Parse the DER SEQUENCE → two INTEGERs → strip leading zeros → pad to 32 bytes
        $pos = 2; // skip SEQUENCE tag + length
        if (ord($der[0]) !== 0x30) return $der;

        // r
        if (ord($der[$pos]) !== 0x02) return $der;
        $pos++;
        $rLen = ord($der[$pos]);
        $pos++;
        $r = substr($der, $pos, $rLen);
        $pos += $rLen;

        // s
        if (ord($der[$pos]) !== 0x02) return $der;
        $pos++;
        $sLen = ord($der[$pos]);
        $pos++;
        $s = substr($der, $pos, $sLen);

        // Remove leading zero bytes (DER padding for positive integers)
        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");

        // Pad to exactly 32 bytes each
        $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
        $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);

        return $r . $s;
    }

    private static function asn1Length(int $length): string
    {
        if ($length < 128) return chr($length);
        if ($length < 256) return "\x81" . chr($length);
        return "\x82" . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
    }
}
