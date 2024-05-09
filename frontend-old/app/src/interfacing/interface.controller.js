angular.module('AmpersandApp')
.controller('InterfaceController', function($scope, $location, ResourceService){
    /*
     * An empty object for typeahead functionality.
     * Defined here so it can be reused in an interface
     * Prevents multiple calls for the same resourceType
     */
    $scope.typeahead = {};
    
    // Detects location changes and checks if there are unsaved changes
    $scope.$on("$locationChangeStart", function(event, next, current){
        if(ResourceService.checkRequired()){
            let confirmed = confirm("You have unsaved edits. Do you wish to leave?");
            if (event && !confirmed) event.preventDefault();
            else if(event && confirmed) ResourceService.emptyUpdatedResources();
            else console.log('Someting went wrong. Cannot determine action after locationChangeStart');
        }
    });
    
    // Function (reference) to check if there are pending promises for a resource
    $scope.pendingPromises = ResourceService.pendingPromises;

    /*
     * Transforms the given variable into an array.
     * To be used in ng-repeat directive for Ampersand UNI and non-UNI expressions
     * If variable is already an array, the array is returned
     * If variable is null, an empty array is returned
     * Otherwise the variable is the first and single item in the array
    */
    $scope.requireArray = function (variable) {
        if (Array.isArray(variable)) {
            return variable;
        } else if (variable === null) {
            return [];
        } else {
            return [variable];
        }
    };
});
