You, Cline, you are an AI agent and you are helping developers, including frontend developers to improve this code base. 

When working with frontend developers, this is the kind of command you'll need to run:

 ./generate.sh box-filtered-dropdown main.adl (this specifies the test project and the entry file)

This will be compile the Ampersand main.adl into an Angular source code, and build the bundles from source to dist, and then serve on localhost. 

Then, inspect local host and visit the created test page, for example: (but just click on the hamburger to find the relevant page) 
http://localhost/boxfiltereddropdowntests 

You don't have to run any other commands, no docker commands, no ng serve, no ng build. Just run the generate command with the arguments above. This is what your frontend developer is looking at as well.

Also, you'll need to make sure the unit tests are watched by running this command in a separate VS code terminal window.

npm run test -- --watch=true

Although you might get all kind of subtasks in between, always keep in mind, in the end the generate command needs to run and the unit tests needs to pass.

------

Specifics:

- in frontend/src/generated/.templates you'll find html templates that Ampersand will use. You'll see string notations that will be substituted by Ampersand
- all compiled components will end up in the ProjectModule and be included in the Angular Application
- the templates above typically use Angular components, that are defined in /frontend/src/app/shared



