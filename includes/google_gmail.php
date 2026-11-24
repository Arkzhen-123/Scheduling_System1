<?php
declare(strict_types=1);

function send_gmail_message(
    string $recipient,
    string $subject,
    string $body
): void {
    $oauth = require __DIR__ . '/../config/google_oauth.php';

    /** @var \Google\Client $client */
    $client = $oauth['client'];

    $tokenFile = $oauth['token_file'];
    $secureDirectory = $oauth['secure_directory'];

    if (!is_file($tokenFile)) {
        throw new RuntimeException(
            'Gmail is not connected. Please connect Gmail in Admin Settings.'
        );
    }

    $tokenContents = file_get_contents($tokenFile);

    if ($tokenContents === false) {
        throw new RuntimeException('The Gmail token could not be read.');
    }

    $token = json_decode($tokenContents, true);

    if (!is_array($token) || empty($token['refresh_token'])) {
        throw new RuntimeException('The Gmail authorization token is invalid.');
    }

    $client->setAccessToken($token);

    if ($client->isAccessTokenExpired()) {
        $refreshToken = $token['refresh_token'];

        $newToken = $client->fetchAccessTokenWithRefreshToken(
            $refreshToken
        );

        if (!is_array($newToken) || isset($newToken['error'])) {
            throw new RuntimeException(
                'Gmail authorization expired. Please reconnect Gmail.'
            );
        }

        // Preserve the refresh token if Google does not return another one.
        if (empty($newToken['refresh_token'])) {
            $newToken['refresh_token'] = $refreshToken;
        }

        $client->setAccessToken($newToken);

        $json = json_encode(
            $newToken,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        if (!is_string($json)) {
            throw new RuntimeException('The refreshed token could not be encoded.');
        }

        $temporaryFile = tempnam($secureDirectory, 'google-token-');

        if ($temporaryFile === false) {
            throw new RuntimeException('A temporary token file could not be created.');
        }

        $written = file_put_contents($temporaryFile, $json, LOCK_EX);

        if ($written === false || $written !== strlen($json)) {
            @unlink($temporaryFile);
            throw new RuntimeException('The refreshed token could not be saved.');
        }

        if (!rename($temporaryFile, $tokenFile)) {
            @unlink($temporaryFile);
            throw new RuntimeException('The refreshed token could not be finalized.');
        }

        @chmod($tokenFile, 0600);
    }

    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('The recipient email address is invalid.');
    }

    $rawMessage =
        'To: ' . $recipient . "\r\n" .
        'Subject: ' . $subject . "\r\n" .
        "MIME-Version: 1.0\r\n" .
        "Content-Type: text/plain; charset=UTF-8\r\n" .
        "Content-Transfer-Encoding: 8bit\r\n" .
        "\r\n" .
        $body;

    $encodedMessage = rtrim(
        strtr(base64_encode($rawMessage), '+/', '-_'),
        '='
    );

    $gmail = new \Google\Service\Gmail($client);

    $message = new \Google\Service\Gmail\Message();
    $message->setRaw($encodedMessage);

    $gmail->users_messages->send('me', $message);
}
