<?php

use League\OAuth2\Client\Token\AccessTokenInterface;

require_once 'vendor/autoload.php';

function getCurrentUrl() : string
{
    if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
        $url = "https://";
    else
        $url = "http://";
    // Append the host(domain name, ip) to the URL.
    $url.= $_SERVER['HTTP_HOST'];

    // Append the requested resource location to the URL
    $url.= strtok($_SERVER['REQUEST_URI'], '?');
    return $url;
}

function checkOrAuthenticate(string $appId, string $appSecret, string $tenant) : array
{
    if (isset($_SESSION['username']) && !ctype_space($_SESSION['username'])) {
        return $_SESSION['username'];
    }
    if (isset($_SESSION['token'])) {
        $token = $_SESSION['token'];
    }
    else {
        $token = authenticate($appId, $appSecret, $tenant);
        $_SESSION['token'] = $token;
    }

    [$user_name, $email, $token] = getUsername($appId, $appSecret, $tenant, $token);
    $_SESSION['username'] = $user_name;
    $_SESSION['email'] = $email;
    $_SESSION['token'] = $token;

    return [$user_name, $email];
}

function getProvider(string $appId, string $appSecret, string $tenant) : TheNetworg\OAuth2\Client\Provider\Azure
{
    $provider = new TheNetworg\OAuth2\Client\Provider\Azure([
        'clientId'          => $appId,
        'clientSecret'      => $appSecret,
        'redirectUri'       => getCurrentUrl(),
        'scopes'            => ['openid'],
        'defaultEndPointVersion' => '2.0',
        'tenant'            => $tenant,
    ]);
    return $provider;
}

function authenticate(string $appId, string $appSecret, string $tenant) : AccessTokenInterface
{
    $provider = getProvider($appId, $appSecret, $tenant);

    if (isset($_GET['code']) && isset($_SESSION['OAuth2.state']) && isset($_GET['state'])) {
        if ($_GET['state'] == $_SESSION['OAuth2.state']) {
            unset($_SESSION['OAuth2.state']);

            // Try to get an access token (using the authorization code grant)
            $token = $provider->getAccessToken('authorization_code', [
                'scope' => $provider->scope,
                'code' => $_GET['code'],
            ]);

            return $token;
        }
        else {
            exit('Invalid state');
        }
    }
    else {
        $authorizationUrl = $provider->getAuthorizationUrl(['scope' => $provider->scope]);
        $_SESSION['OAuth2.state'] = $provider->getState();
        header('Location: ' . $authorizationUrl);
        exit;
    }
}

function getUsername(string $appId, string $appSecret, string $tenant, AccessTokenInterface $token): array
{
    $provider = getProvider($appId, $appSecret, $tenant);

    if ($token->hasExpired()) {
        if (!is_null($token->getRefreshToken())) {
            $token = $provider->getAccessToken('refresh_token', [
                'scope' => $provider->scope,
                'refresh_token' => $token->getRefreshToken()
            ]);
        }
        else {
            $token = null;
        }
    }

    // We got an access token, let's now get the user's details
    $user = $provider->get($provider->getRootMicrosoftGraphUri($token) . '/v1.0/me', $token);

    // Use this to interact with an API on the users behalf
    return [$user['displayName'], $user['mail'], $token];
}

function logout(string $appId, string $appSecret, string $tenant)
{
    $logout_url = "https://$tenant/";
    if (isset($_SESSION['username'])) {
        $provider = getProvider($appId, $appSecret, $tenant);
        $logout_url = $provider->getLogoutUrl($logout_url);
    }
    session_destroy();
    header('Location: '.$logout_url);
    exit();
}
