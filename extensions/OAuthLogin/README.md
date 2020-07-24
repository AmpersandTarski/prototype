# The OAuthLogin extension

## Purpose of the extension
The OAuthLogin extension allows you to easily add a user (account) registration to your Ampersand prototype.

## How to INSTALL the extension
* Run 'gulp project' command after generating the prototype to include javascript and html resources
* Include the extension in your project.yaml file. See below

## How to CONFIGURE the extension
Step 1: Choose identity providers you would like to support (e.g. Github, LinkedIn, Google, etc)
    - Register application at identity provider
    - Configure supported identity providers in a config yaml file (see sampleConfig.yaml)
    - Place the config file in the /config folder

Step 2: Add the following part to the project.yaml under 'extensions' to include the extensions
    OAuthLogin:
      bootstrap: extensions/OAuthLogin/bootstrap.php
      config: config/oauth.yaml

Step 3: Add required concepts and relations to your Ampersand script. See SIAM OAuth module.

Step 4 (optional): Test the OAuth protocol on your local machine:
    - add example.com to redirect to localhost in host table (c:/windows/system32/drivers/etc/hosts)
    - run cmd: ipconfig /flushdns
    - restart browser and check if example.com redirect to your local machine
    - replace 'https://[server]' in config yaml with example.com

## Notes
* The OAuthLogin extension uses [curl](http://php.net/manual/en/book.curl.php) to get/request data from the identity providers. To verify the peer curl needs a file with root certificates, which is provided in the cacerp.pem file in this folder. Goto https://curl.haxx.se/docs/caextract.html to update the cacert.pem once in a while. During Docker build the latest version of cacert.pem is downloaded automatically.