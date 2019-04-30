angular.module('AmpersandApp')
.directive('myNavItem', function(){
    return {
        restrict : 'A',
        scope: {
            item: '=data' // '=' => two-way bind
        },
        templateUrl: 'app/src/navbar/myNavItem.view.html',
        transclude: false,
        link: function(scope, element, attrs, controller){
            // Functionality to add/remove class 'dropdown-submenu' when item is moved to/from overflow list
            scope.$watch(function() {
                return element.attr('class');
            }, function(){
                if (scope.item.hasChildren() && element.hasClass('overflow-menu-item')) {
                    element.addClass('dropdown-submenu');
                } else if (scope.item.hasChildren() && element.hasClass('top-menu-item')) {
                    element.removeClass('dropdown-submenu');
                }
            });
        },
        controller: function ($scope) {
            
        }
    };
});