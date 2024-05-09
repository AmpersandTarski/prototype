angular.module('AmpersandApp')
.config(function($routeProvider) {
    $routeProvider
        .when('/ext/importer', {
            controller : 'PopulationImportController',
            templateUrl : 'app/src/importer/importer.html',
            interfaceLabel : 'Population importer'
        });
}).service('ImportService', function(FileUploader, NotificationService, NavigationBarService){
    let uploader = new FileUploader({
        url: 'api/v1/admin/import'
    });

    uploader.onSuccessItem = function(fileItem, response, status, headers) {
        NotificationService.updateNotifications(response.notifications);
        if(response.sessionRefreshAdvice) NavigationBarService.refreshNavBar();
    };
    
    uploader.onErrorItem = function(item, response, status, headers){
        let message;
        let details;
        if(typeof response === 'object'){
            if (response.notifications !== undefined) {
                NotificationService.updateNotifications(response.notifications);
            }
            message = response.msg || 'Error while importing';
            NotificationService.addError(message, status, true, response.html);
        }else{
            message = status + ' Error while importing';
            details = response; // html content is excepted
            NotificationService.addError(message, status, true, details);
        }
    };
    
    return {uploader : uploader};
}).controller('PopulationImportController', function ($scope, ImportService) {
    $scope.uploader = ImportService.uploader;
}).requires.push('angularFileUpload'); // add angularFileUpload to dependency list
