angular.module('AmpersandApp')
.controller('NotificationCenterController', function ($scope, $route, Restangular, $localStorage, NotificationService) {
    
    $scope.localStorage = $localStorage;
    $scope.notifications = NotificationService.notifications;
    
    // Hide success-, error-, warnings-, info- and invariant violation messages (not signals) upon route change
    $scope.$on("$routeChangeSuccess", function(){
        $scope.notifications.successes = [];
        $scope.notifications.errors = $scope.notifications.errors.filter(function (error){
            if(error.persistent){
                error.persistent = false;
                return true;
            }
            else return false;
        });
        // $scope.notifications.warnings = []; // leave warnings in screen until user dismisses them
        $scope.notifications.infos = [];
        $scope.notifications.invariants = [];
    });
    
    // Function to close notifications
    $scope.closeAlert = function(alerts, index) {
        alerts.splice(index, 1);
    };
    
});
