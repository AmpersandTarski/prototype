angular.module('AmpersandApp')
.service('LoginService', function($rootScope, $location, $localStorage, $sessionStorage){
    let urlLoginPage = null;
    
    let service = {
        setLoginPage : function (url) {
            urlLoginPage = url;
        },

        gotoLoginPage : function () {
            if (urlLoginPage) {
                $location.url(urlLoginPage);
            }
        },

        getPageBeforeLogin : function () {
            return $localStorage.login_urlBeforeLogin;
        },

        sessionIsLoggedIn : function () {
            return $sessionStorage.session.loggedIn;
        },

        setSessionIsLoggedIn : function (bool) {
            $sessionStorage.session.loggedIn = bool;
        },

        getSessionVars : function () {
            return $sessionStorage.sessionVars;
        }
    };

    $rootScope.$on('$routeChangeSuccess', function (event, current, previous) {
        if (current.$$route.originalPath !== urlLoginPage) {
            $localStorage.login_urlBeforeLogin = $location.path();
        } else {
            console.log('login page');
        }
    });
    
    return service;
});