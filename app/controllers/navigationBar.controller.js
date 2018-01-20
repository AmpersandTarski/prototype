angular.module('AmpersandApp').controller('NavigationBarController', function ($scope, $rootScope, $route, Restangular, $localStorage, $sessionStorage, $timeout, $location, NotificationService, RoleService) {
    
    $scope.$storage = $localStorage;
    $scope.$sessionStorage = $sessionStorage;
    $scope.defaultSettings = {};
    
    $rootScope.loadingNavBar = []; // initialize an array for promises, used by angular-busy module (loading indicator)
    
    $rootScope.setActiveRoles = function(){
        $rootScope.loadingNavBar = [];
        $rootScope.loadingNavBar.push(
            Restangular.all('app/roles')
            .patch($sessionStorage.sessionRoles)
            .then(function(data){
                $rootScope.refreshNavBar();
            })
        );
    };
    
    $rootScope.refreshNavBar = function(){
        $rootScope.loadingNavBar = [];
        $rootScope.loadingNavBar.push(
            Restangular.one('app/navbar')
                .get()
                .then(function(data){
                    data = data.plain();
                    $rootScope.navbar = data;
                    $scope.$sessionStorage.session = data.session;
                    $scope.$sessionStorage.sessionRoles = data.sessionRoles;
                    $scope.$sessionStorage.sessionVars = data.sessionVars;
                    
                    $scope.defaultSettings = data.defaultSettings;
                    
                    // Default settings for notificationPrefs
                    if($scope.$storage.notificationPrefs === undefined){
                        $scope.resetNotificationSettings();
                    }
                    // Default setting for switchAutoSave
                    if($scope.$storage.switchAutoSave === undefined){
                        $scope.resetSwitchAutoSave();
                    }
                    
                    // Default setting for cacheGetCalls
                    if($scope.$storage.cacheGetCalls === undefined){
                        $scope.$storage.cacheGetCalls = $scope.defaultSettings.cacheGetCalls;
                    }
                    
                    // Update notifications
                    NotificationService.updateNotifications(data.notifications);
                }, function(error){
                    // on error
                })
        );
    };
    
    $scope.reload = function(){
        $scope.refreshNavBar();
        $route.reload();
    };

    $scope.createNewResource = function(resourceType, openWithIfc){
        Restangular.one('resource').all(resourceType)
        .post({}, {})
        .then(
            function(data){
                // Jumps to interface and requests newly created resource
                $location.url(openWithIfc + '/' + data._id_);
            }
        )
    };
    
    $scope.resetSettings = function(){
        // all off
        $.each($scope.$storage.notificationPrefs, 
            function(index, value){
                $scope.$storage.notificationPrefs[index] = false;
            }
        );
        $scope.$storage.switchAutoSave = false;
        
        $timeout(function() {
            // reset to default        
            $scope.resetNotificationSettings();
            $scope.resetSwitchAutoSave();
        }, 500);
    };
    
    $scope.resetNotificationSettings = function(){
        $scope.$storage.notificationPrefs = $.extend($scope.$storage.notificationPrefs, $scope.defaultSettings.notifications);
    };
    
    $scope.resetSwitchAutoSave = function(){
        $scope.$storage.switchAutoSave = $scope.defaultSettings.switchAutoSave;
    };
    
    $scope.$watch('$storage.cacheGetCalls', function() {
        Restangular.setDefaultHttpFields({cache: $scope.$storage.cacheGetCalls });
    });
    
    $rootScope.refreshNavBar(); // initialize navbar
}).directive('myNavbarResize', function ($window, $rootScope, $timeout) {
    return function (scope, element) {
        var w = angular.element($window);
        
        var resizeNavbar = function() {
            $timeout(function(){
                // moving ifc items from dropdown-menu to navbar itself
                while($('#navbar-interfaces').width() < ($('#navbar-wrapper').width() - $('#navbar-options').width()) &&
                        $('#navbar-interfaces-dropdown-menu').children().length > 0){
                    $("#navbar-interfaces-dropdown-menu").children().first().appendTo("#navbar-interfaces");
                }
                
                // moving ifc items from navbar to dropdown-menu
                while($('#navbar-interfaces').width() > ($('#navbar-wrapper').width() - $('#navbar-options').width())){
                    $("#navbar-interfaces").children().last().prependTo("#navbar-interfaces-dropdown-menu");
                    
                    // show/hide dropdown menu for more interfaces (must be inside loop, because it affects the width of the navbar
                    $('#navbar-interfaces-dropdown').toggleClass('hidden', $('#navbar-interfaces-dropdown-menu').children().length <= 0);
                }
                
                // show/hide dropdown menu when possible
                $('#navbar-interfaces-dropdown').toggleClass('hidden', $('#navbar-interfaces-dropdown-menu').children().length <= 0);
            });
        };
        
        // watch navbar
        $rootScope.$watch('navbar', function() {
            resizeNavbar();
        });
        
        // when window size gets changed
        w.bind('resize', function () {        
            resizeNavbar();
        });
        
        // when page loads
        resizeNavbar();
    };
});