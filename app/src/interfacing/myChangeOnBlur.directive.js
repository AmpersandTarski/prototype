// Adapted from https://stackoverflow.com/questions/20441779/angularjs-blur-changed
angular.module('AmpersandApp')
.directive('myChangeOnBlur', function() {
    return {
        restrict: 'A',
        require: 'ngModel',
        link: function(scope, elm, attrs, ngModelCtrl) {
            // Does not work for radio and checkbox input types
            if (attrs.type === 'radio' || attrs.type === 'checkbox') {
                return;
            }

            let expressionToCall = attrs.myChangeOnBlur;
            let oldValue = null;

            // Store value on element focus (e.g. entering field)
            elm.bind('focus', (event) => {
                scope.$apply(() => {
                    oldValue = elm.val();
                });
            });

            // On blur check if value is changed and call expression
            elm.bind('blur', (event) => {
                scope.$apply(() => {
                    let newValue = elm.val();
                    if (newValue !== oldValue){
                        scope.$eval(expressionToCall);
                    }
                });
            });
        }
    };
});
