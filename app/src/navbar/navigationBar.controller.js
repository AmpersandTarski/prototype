angular.module('AmpersandApp')
.controller('NavigationBarController', function ($scope, $route, Restangular, $localStorage, $sessionStorage, $location, NotificationService, RoleService, NavigationBarService, LoginService) {
    $scope.localStorage = $localStorage;
    $scope.loadingNavBar = [];
    $scope.navbar = NavigationBarService.navbar;
    $scope.resetSettingsToDefault = NavigationBarService.resetSettingsToDefault;
    
    $scope.loggedIn = function () {
        return LoginService.sessionIsLoggedIn();
    };

    $scope.getSessionRoles = function () {
        return $sessionStorage.sessionRoles;
    };

    $scope.getSessionVars = function () {
        return $sessionStorage.sessionVars;
    };

    $scope.reload = function(){
        $scope.loadingNavBar = [];
        $scope.loadingNavBar.push(NavigationBarService.refreshNavBar());
        $route.reload();
    };

    $scope.toggleRole = function(roleId, set){
        RoleService.toggleRole(roleId, set);
        $scope.loadingNavBar = [];
        $scope.loadingNavBar.push(
            RoleService.setActiveRoles()
            .then(function(data){
                NavigationBarService.refreshNavBar();
                $route.reload();
            })
        );
    };

    $scope.checkAllRules = NotificationService.checkAllRules;

    $scope.createNewResource = function(resourceType, openWithIfc){
        Restangular.one('resource').all(resourceType)
        .post({}, {})
        .then(
            function(data){
                // Jumps to interface and requests newly created resource
                $location.url(openWithIfc + '/' + data._id_);
            }
        );
    };
    
    $scope.loadingNavBar.push(NavigationBarService.refreshNavBar());
});
