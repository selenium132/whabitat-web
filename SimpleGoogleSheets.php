<?php
class SimpleGoogleSheets {
    private $credentials;
    private $accessToken;

    public function __construct($jsonPath) {
        if (!file_exists($jsonPath)) {
            throw new Exception("Credential file not found: $jsonPath");
        }
        $this->credentials = json_decode(file_get_contents($jsonPath), true);
    }

    private function getAccessToken() {
        if ($this->accessToken) return $this->accessToken;

        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $now = time();
        $claim = json_encode([
            'iss' => $this->credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/spreadsheets https://www.googleapis.com/auth/drive.file',
            'aud' => $this->credentials['token_uri'],
            'exp' => $now + 3600,
            'iat' => $now
        ]);


        $base64Header = $this->base64UrlEncode($header);
        $base64Claim = $this->base64UrlEncode($claim);
        $dataToSign = $base64Header . "." . $base64Claim;

        $signature = '';
        $success = openssl_sign($dataToSign, $signature, $this->credentials['private_key'], "SHA256");

        if (!$success) {
            throw new Exception("Failed to sign JWT.");
        }

        $jwt = $dataToSign . "." . $this->base64UrlEncode($signature);

        // Exchange JWT for Access Token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->credentials['token_uri']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        
        if (!isset($data['access_token'])) {
            throw new Exception("Failed to get access token: " . $response);
        }

        $this->accessToken = $data['access_token'];
        return $this->accessToken;
    }

    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function callApi($method, $url, $body = null) {
        $token = $this->getAccessToken();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new Exception("API Error ($httpCode): " . $response);
        }

        return json_decode($response, true);
    }

    public function createSpreadsheet($title) {
        $url = "https://sheets.googleapis.com/v4/spreadsheets";
        $body = ['properties' => ['title' => $title]];
        return $this->callApi('POST', $url, $body);
    }

    public function clearValues($spreadsheetId, $range) {
        $url = "https://sheets.googleapis.com/v4/spreadsheets/$spreadsheetId/values/$range:clear";
        return $this->callApi('POST', $url, new stdClass()); // Empty body
    }

    public function updateValues($spreadsheetId, $range, $values) {
        $url = "https://sheets.googleapis.com/v4/spreadsheets/$spreadsheetId/values/$range?valueInputOption=RAW";
        $body = ['values' => $values];
        return $this->callApi('PUT', $url, $body);
    }

    public function addPermission($fileId, $role, $type, $email = null) {
        $url = "https://www.googleapis.com/drive/v3/files/$fileId/permissions";
        $body = [
            'role' => $role,
            'type' => $type
        ];
        if ($email) {
            $body['emailAddress'] = $email;
        }
        return $this->callApi('POST', $url, $body);
    }
}
?>
