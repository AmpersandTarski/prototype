<?php

require_once (__DIR__ . '/../../../fw/includes.php');

// Create and configure Slim app (version 2.x)
$app = new \Slim\Slim(array(
    'debug' => Config::get('debugMode')
));

$app->add(new \Slim\Middleware\ContentTypes());
$app->response->headers->set('Content-Type', 'application/json');

// Error handler
$app->error(function (Exception $e) use ($app) {
	$app->response->setStatus($e->getCode());
	print json_encode(array('error' => $e->getCode(), 'msg' => $e->getMessage()));
});

// Not found handler
$app->notFound(function () use ($app) {
	$app->response->setStatus(404);
	print json_encode(array('error' => 404, 'msg' => "Not found"));
});

$app->get('/run', function () use ($app){
	$session = Session::singleton();
	
	$roleIds = $app->request->params('roleIds');
	$session->activateRoles($roleIds);
	
	// Check sessionRoles if allowedRolesForRunFunction is specified
	$allowedRoles = Config::get('allowedRolesForRunFunction','execEngine');
	if(!is_null($allowedRoles)){
		$ok = false;
	
		foreach($session->getSessionRoles() as $role){
			if(in_array($role->label, $allowedRoles)) $ok = true;
		}
		if(!$ok) throw new Exception("You do not have access to run the exec engine", 401);
	}
		
	ExecEngine::run(true);
	
	$session->database->closeTransaction('Run completed', true);
		
	$result = array('notifications' => Notifications::getAll());
	
	print json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
});

// Run app
$app->run();

?>