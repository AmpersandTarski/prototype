angular.module('AmpersandApp')
.directive('myNavItem', function(){
    return {
        restrict : 'A',
        scope: {
            item: '=data' // '=' => two-way bind
        },
        templateUrl: 'app/src/navbar/myNavItem.view.html',
        transclude: false,
        controller: function ($scope) {
            
        }
    };
});