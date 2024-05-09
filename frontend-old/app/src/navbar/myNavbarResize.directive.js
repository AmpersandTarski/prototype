angular.module('AmpersandApp')
.directive('myNavbarResize', function ($window, $timeout, NavigationBarService) {
    return function (scope, element) {
        var w = angular.element($window);
        
        var resizeNavbar = function() {
            $timeout(function(){
                // moving ifc items from dropdown-menu to navbar itself
                while($('#navbar-interfaces').width() < ($('#navbar-wrapper').width() - $('#navbar-options').width()) &&
                        $('#navbar-interfaces-dropdown-menu').children().length > 0){
                    $("#navbar-interfaces-dropdown-menu")
                    .children().first().appendTo("#navbar-interfaces") // move item back to menu bar
                    .toggleClass('overflow-menu-item', false); // remove flag specifying that this item is in the overflow list
                }
                
                // moving ifc items from navbar to dropdown-menu
                while($('#navbar-interfaces').width() > ($('#navbar-wrapper').width() - $('#navbar-options').width())){
                    $("#navbar-interfaces")
                    .children().last().prependTo("#navbar-interfaces-dropdown-menu") // move item to overflow list
                    .toggleClass('overflow-menu-item', true); // add flag specifying that this item is in the overflow list
                    
                    // show/hide dropdown menu for more interfaces (must be inside loop, because it affects the width of the navbar
                    $('#navbar-interfaces-dropdown').toggleClass('hidden', $('#navbar-interfaces-dropdown-menu').children().length <= 0);
                }
                
                // show/hide dropdown menu when possible
                $('#navbar-interfaces-dropdown').toggleClass('hidden', $('#navbar-interfaces-dropdown-menu').children().length <= 0);
            });
        };
        
        // watch navbar
        NavigationBarService.addObserverCallable(resizeNavbar);
        
        // when window size gets changed
        w.bind('resize', function () {
            resizeNavbar();
        });
        
        // when page loads
        angular.element(document).ready(function(){
            resizeNavbar();
        });
    };
});
