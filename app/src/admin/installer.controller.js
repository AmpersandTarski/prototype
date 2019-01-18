angular.module('AmpersandApp')
.controller('InstallerController', function ($scope, Restangular, NotificationService, RoleService, NavigationBarService) {
    $scope.installing = false;
    $scope.installed = false;
    
    $scope.install = function(defPop, ignoreInvariantRules){
        $scope.installing = true;
        $scope.installed = false;
        NotificationService.clearNotifications();
        
        Restangular
        .one('admin/installer')
        .get({defaultPop : defPop, ignoreInvariantRules : ignoreInvariantRules})
        .then(function(data) {
            data = data.plain();
            NotificationService.updateNotifications(data);
            NavigationBarService.refreshNavBar();
            
            // deactive all roles
            RoleService.deactivateAllRoles();
            
            $scope.installing = false;
            $scope.installed = true;
        }, function(){
            $scope.installing = false;
            $scope.installed = false;
        });
    };
});
