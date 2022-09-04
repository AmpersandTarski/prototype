# Event Dispatcher

## Introduction
The prototype framework dispatches important events in order for other software projects to extend the framework. An example of such an event is the AtomEvent that is dispatched upon added and deleted atoms. 

The event dispatcher is the central object in the event dispatching system and is set as a property of the AmpersandApp class which is everywhere available in the codebase. You can access the event dispatcher like so:
```php
<?php
/** @var AmpersandApp $app */
$dispatcher = $app->eventDispatcher();
$dispatcher->dispatch();
```

## The Symfony event dispatcher component
Currently we use the Symfony event dispatcher component as implementatation. Documentation can be found [here](https://symfony.com/doc/master/components/event_dispatcher.html#introduction).

> "The Symfony EventDispatcher component implements the Mediator and Observer design patterns to make all these things possible and to make your projects truly extensible."

## Dispatched events
Below a list of dispatched events. More events will be added upon request. Please create an issue for that in the repository.

| Event class | Event name | Comment |
| ----------- | ---------- | ------- |
| AtomEvent   | ADDED | When a new (non-existing) atom is created
| AtomEvent   | DELETED | When an atom is deleted
| LinkEvent   | ADDED | When a new (non-existing) link is created
| LinkEvent   | DELETED | When a link is deleted
| TransactionEvent | STARTED | When a new Ampersand transaction is created/started
| TransactionEvent | COMMITTED | When an Ampersand transaction is committed (i.e. invariant rules hold)
| TransactionEvent | ROLLEDBACK | When an Ampersand transaction is rolled back (i.e. invariant rules do not hold)

## Adding a listener
You can easily connect a listener to the dispatcher so that it can be notified/called when certain events are dispatched. A listener can be any valid [PHP callable](https://www.php.net/manual/en/language.types.callable.php).

See [documentation of Symfony](https://symfony.com/doc/master/components/event_dispatcher.html#connecting-listeners)

Below two examples of connecting a listener to the atom added event
```php
<?php
// Listener class method example
class MyListener
{
    public function methodToCall(AtomEvent $event) {}
}

$listener = new MyListener();
$dispatcher->addListener(AtomEvent::ADDED, [$listener, 'methodToCall']);
```

```php
<?php
// Closure example (i.e. anonymous function)
$dispatcher->addListener(AtomEvent::ADDED, function (AtomEvent $event) {
    /* code here */
});
```
