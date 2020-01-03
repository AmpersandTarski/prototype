angular.module('AmpersandApp')
.config(function($routeProvider) {
    $routeProvider
        // default start page
        .when('/ext/Login', {
            resolveRedirectTo : ['LoginService', function (LoginService) {
                if (LoginService.sessionIsLoggedIn()) {
                    return '/'; // nav to home when user is already loggedin
                } else {
                    return; // will continue this route using controller and template below
                }
            }],
            controller : 'LoginExtLoginController',
            templateUrl : 'app/ext/OAuthLogin/views/Login.html',
            interfaceLabel : 'Login'
        });
}).requires.push('LoginModule'); // add LoginModule to dependency list

// LoginModule declaration
angular.module('LoginModule', ['ngRoute', 'restangular'])
.controller('LoginExtLoginController', function($scope, Restangular, $location, NotificationService, LoginService){
    // When already logged in, navigate to home
    $scope.$watch(LoginService.sessionIsLoggedIn(), function() {
        if (LoginService.sessionIsLoggedIn()) {
            $location.path('/'); // goto home
        }
    });

    Restangular.one('oauthlogin/login').get().then(
        function(data){ // on success
            $scope.idps = data.identityProviders;
            NotificationService.updateNotifications(data.notifications);
        }
    );
}).controller('LoginExtLogoutController', function($scope, Restangular, $location, NotificationService, NavigationBarService){
    $scope.logout = function(){
        Restangular.one('oauthlogin/logout').get().then(
            function(data){ // success
                NotificationService.updateNotifications(data.notifications);
                NavigationBarService.refreshNavBar();
                $location.path('/'); // goto home
            }
        );
    };
});