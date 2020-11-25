<?php

namespace Ampersand\SIAM\OAuth;

use Ampersand\AmpersandApp;
use Exception;
use Ampersand\Interfacing\ResourceList;

class LoginController
{
    private $token_url;
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    protected AmpersandApp $ampersandApp;
    protected string $cacertLocation;

    private $tokenObj;
    private $dataObj;

    public function __construct($client_id, $client_secret, $redirect_uri, $token_url, AmpersandApp $app)
    {
        $this->ampersandApp = $app;
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->redirect_uri = $redirect_uri;
        $this->token_url = $token_url;
        $this->cacertLocation = $this->ampersandApp->getSettings()->get('oauthlogin.cacertLocation', '/etc/ssl/certs/cacert.pem');
    }

    public function requestToken($code)
    {
        // Setup token request
        $token_request = [
            'token_url' => $this->token_url,
            'arguments' => [
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->redirect_uri
            ]
        ];

        // Make HTTP POST request to OAUTH host to get token
        $curl = curl_init();
        curl_setopt_array($curl, [ CURLOPT_RETURNTRANSFER => 1
                                 , CURLOPT_URL => $token_request['token_url']
                                 , CURLOPT_USERAGENT => $this->ampersandApp->getName()
                                 , CURLOPT_POST => 1
                                 , CURLOPT_POSTFIELDS => http_build_query($token_request['arguments'])
                                 , CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json']
                                 , CURLOPT_CAINFO => $this->cacertLocation
                                 ]);

        // Send the request & save response to $resp
        $token_resp = curl_exec($curl);

        // Check if response is received:
        if (!$token_resp) {
            throw new Exception('Error: "' . curl_error($curl) . '" - Code: ' . curl_errno($curl), 500);
        }

        // Close request to clear up some resources
        curl_close($curl);
        
        // Decode token JSON response to stdObj and return
        $this->tokenObj = json_decode($token_resp);
        
        if (!isset($this->tokenObj->access_token)) {
            $error = "Error: Someting went wrong getting token, '" . $this->tokenObj->error . "'";
            if (isset($this->tokenObj->error_description)) {
                $error .= " Description: '" . $this->tokenObj->error_description . "'";
            }
            throw new Exception($error, 500);
        }
        
        return true;
    }

    public function requestData($api_url)
    {
        if (!isset($this->tokenObj)) {
            throw new Exception("Error: No token set", 500);
        }
        
        // Do a HTTP HEADER request to the API_URL
        $curl = curl_init();
        curl_setopt_array($curl, [ CURLOPT_RETURNTRANSFER => 1
                                 , CURLOPT_URL => $api_url
                                 , CURLOPT_USERAGENT => $this->ampersandApp->getName()
                                 , CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->tokenObj->access_token, 'x-li-format: json']
                                 , CURLOPT_CAINFO => $this->cacertLocation
                                 ]);

        // Execute request
        $data_resp = curl_exec($curl);
        
        // Check if response is received:
        if (!$data_resp) {
            throw new Exception('Error: "' . curl_error($curl) . '" - Code: ' . curl_errno($curl), 500);
        }
        
        // Close request to clear up some resources
        curl_close($curl);

        // Return data
        return $this->dataObj = json_decode($data_resp);
    }

    public function getData()
    {
        if (!isset($this->dataObj)) {
            return false;
        } else {
            return $this->dataObj;
        }
    }
    
    /**
     * Process authentication request
     *
     * @param string $code
     * @param string $idp
     * @param string $api_url
     * @return bool
     */
    public function authenticate(string $code, string $idp, $api_url): bool
    {
        if (empty($code)) {
            throw new Exception("No authentication code provided. Please try again", 400);
        }

        // request token
        if ($this->requestToken($code)) {
            // request data
            if ($this->requestData($api_url)) {
                // Get email here
                $email = null;
                switch ($idp) {
                    case 'linkedin':
                        // Linkedin provides primary emailaddress only. This is always a verified address.
                        // https://docs.microsoft.com/en-us/linkedin/consumer/integrations/self-serve/sign-in-with-linkedin?context=linkedin/consumer/context
                        $data = $this->getData()->elements[0];
                        if (!isset($data->{'handle~'})) {
                            throw new Exception("Error in getting verified emailadres from LinkedIn");
                        }
                        if (!isset($data->{'handle~'}->emailAddress)) {
                            throw new Exception("Error in getting verified emailadres from LinkedIn: emailAddres not provided");
                        }
                        $email = $data->{'handle~'}->emailAddress;
                        break;
                    case 'google':
                        $email = $this->getData()->email;
                        if (!$this->getData()->verified_email) {
                            throw new Exception("Google emailaddress is not verified", 500);
                        }
                        break;
                    case 'github':
                        foreach ($this->getData() as $data) {
                            if ($data->primary && $data->verified) {
                                $email = $data->email;
                            }
                        }
                        if (is_null($email)) {
                            throw new Exception("Github primary emailaddress is not verified", 500);
                        }
                        break;
                    default:
                        throw new Exception("Unknown identity provider", 500);
                        break;
                }
                
                return $this->login($email);
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    
    /**
     * Login user by verified emailadres
     * Return true on login, false otherwise
     *
     * @param string $email
     * @return boolean
     */
    private function login(string $email): bool
    {
        if (empty($email)) {
            throw new Exception("No emailaddress provided to login", 500);
        }
        
        $accounts = ResourceList::makeFromInterface($email, 'AccountForUserid')->getResources();
        
        // Create new account
        if (empty($accounts)) {
            $account = $this->ampersandApp->getModel()->getConceptByLabel('Account')->createNewAtom();
            
            // Save email as accUserid
            $account->link($email, 'accUserid[Account*UserID]')->add();
            
            try {
                // If possible, add account to organization(s) based on domain name
                $domain = explode('@', $email)[1];
                $orgs = ResourceList::makeFromInterface($domain, 'DomainOrgs')->getResources();
                foreach ($orgs as $org) {
                    $account->link($org, 'accOrg[Account*Organization]')->add();
                }
            } catch (Exception $e) {
                // Domain orgs not supported => skip
            }
        } elseif (count($accounts) == 1) {
            $account = current($accounts);
        } else {
            // This SHOULD NOT happen
            throw new Exception("Multiple users registered with email $email", 500);
        }
        
        // Login account
        $transaction = $this->ampersandApp->getCurrentTransaction();
        $this->ampersandApp->login($account); // Automatically closes transaction

        if ($transaction->isCommitted()) {
            $this->ampersandApp->userLog()->notice("Login successfull");
            return true;
        } else {
            return false;
        }
    }
}
