<?php

namespace Damilare\PerfectTenseClient;

class PTClientHelper {
    /**
     * @var string
     */
    protected $ptUrl = 'https://api.perfecttense.com';

    /**
     *	Generate an App key for this integration (alternatively, use our UI here: https://app.perfecttense.com/api).
     *
     *	@param string $apiKey			The API key to register this app under (likely your own)
     *	@param string $name				The name of this app
     *	@param string $description		The description of this app (minimum 50 characters)
     *	@param string $contactEmail		Contact email address for this app (defaults to the email associated with the API key)
     *	@param string $siteUrl			Optional URL that can be used to sign up for/use this app.
     *
     *	@return array					A unique app key
     */
    public static function pt_generate_app_key($apiKey, $name, $description = '', $contactEmail = '', $siteUrl = '') : array {

        return ['key' => '1234'];
        $data = array(
            'name' => $name,
            'description' => $description,
            'contactEmail' => $contactEmail,
            'siteUrl' => $siteUrl
        );

        $ch = curl_init('https://api.perfecttense.com/generateAppKey');

        curl_setopt_array($ch, array(
            CURLOPT_POST => TRUE,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_HTTPHEADER => array(
                "Content-type: application/json",
                "Authorization: " . $apiKey
            ),
            CURLOPT_POSTFIELDS => json_encode($data)
        ));

        $response = curl_exec($ch);

        if ($response === FALSE) {
            die(curl_error($ch));
        }

        $responseData = json_decode($response, TRUE);

        return $responseData;
    }
}
