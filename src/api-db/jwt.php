<?php

// This should be generated once, offline, then stored in a file that is loaded
// into your app. You don't want this uploaded to, say, GitHub, in any kind of
// real application. I generated this using the PHP interactive command line
// tool (php -a) by entering:
//      $bytes = random_bytes(20);
//      var_dump(bin2hex($bytes));
$SECRET = "9aa52d236db958122c4be72729a4a870e305a6db";

/**
 * Creates a JWT encrypted with HS256 (SHA256). 
 * 
 * @param payload An associative array of claims and values.
 * @param secret The secret to use for signing.
 * @return A JWT including the given payload signed with the given secret.
 */
function makeJWT($payload, $secret){
    $header = ['typ' => 'JWT', 'alg' => 'HS256'];
    $encodedHeader =  base64url_encode(json_encode($header));
    $encodedPayload = base64url_encode(json_encode($payload));
    $encodedSignature = base64url_encode(hash_hmac('sha256', 
        join('.', [$encodedHeader, $encodedPayload]), $secret, true));
    return join('.', [$encodedHeader, $encodedPayload, $encodedSignature]);
}

/**
 * Encodes the given string in base64, but swaps out '+', '/', and '=' for
 * '-', '_', and ''. 
 * 
 * @param str The string to encode.
 * @return The encoded string.
 */
function base64url_encode($str){
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($str));
}

/**
 * Breaks apart and decodes a JWT into header, payload, and signature and
 * assembles them into an assoiative array with three keys:
 *   - header
 *   - payload
 *   - verified -- true if the signature matches
 * 
 * @param jwt The JWT to decode and verify.
 * @param secret The secret to use when verify the signature.
 * @return The associative array described above.
 */
function verifyJWT($jwt, $secret){
    $jwtParts = preg_split('/\./', $jwt);
    $head = json_decode(base64url_decode($jwtParts[0]), $assoc = true);
    $payload = json_decode(base64url_decode($jwtParts[1]), $assoc = true);
    $sig = base64url_decode($jwtParts[2]);
    return [
        'header' => $head,
        'payload' => $payload,
        'verified' => hash_hmac("sha256", 
            join('.', array_slice($jwtParts, 0, 2)), $secret, true) === $sig
    ];
}

/**
 * Decodes the given base64 url string.
 * 
 * @param str The string to decode.
 * @return The decoded string.
 */
function base64url_decode($str){
    return base64_decode(str_replace(['-', '_'], ['+', '/'], $str));
}

/**
 * Checks whether the timestamp in the 'exp' claim in the payload has passed.
 * 
 * @param jwtData An object output by `verifyJWT`.
 * @return true if the 'exp' claim in the payload is in the past, false otherwise.
 */
function isExpired($jwtData){
    return (new DateTime($jwtData['payload']['exp'])) <= (new DateTime('NOW'));
}


?>