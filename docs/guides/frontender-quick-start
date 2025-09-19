# How to develop frontend components?

## Developing Ampersand Components
### Get it running

For BOX and ATOMIC components, stored in the frontend/src/app/shared folder, follow these steps: 

1. Install Docker

2. Open the prototype framework in VS Code

3. Run: 
> Docker compose up -d -- build
this will use the compose.yaml in root folder and creates containers for the ampersand compiler, database and phpmyadmin.

<!-- 4. Open a New VS Code Window -->

<!-- 5. Open the project again, but now in a dev container  -->
<!-- (this will use the devcontainer.json and Dockerfile in the .devcontainer folder to open an environment that works on Mac Intel/Mac Apple Silicon, and Windows)
(dev containers are temporary disabled) -->

6. Run the hello world project
./generate.sh 
(the default is the hello world app)

7. Visit localhost
http://localhost

The Hello World App or your Ampersand app should now be running.

If you already had localhost open in your browser, refresh your page

Optional steps:
8. Clean/Refresh your application
The prototype framework might hold an 'old' application. You might see that in the navigation bar through items that not belong to the 'Hello World' app. Also, you might receive some popup errors. In that case

9. Install application 
Click on 'Installer', followed by 'With Default Population'. 

10. Visit localhost
http://localhost


### Add your own test project
Now it's time to specify your own test project.

1. Create your project
Create your project in /test/projects and put your adl entry file in the model subdirectory. Take the existing projects as an example. 

2. Run your test project
./generate.sh <project-name> <entry-file>

for example: 
./generate.sh box-filtered-dropdown main.adl

This will compile your ADL to an actual application running the browser.

### Make changes: your first component

For a new Ampersand construct, you'll need to create a new template manually. Add your template to:

src/app/generated/.template 

Don't feel confused that you are adding something manual to a 'generated' folder. Maybe counter intuitive, but that's how it works

Templates will eventually bind to Angular components, typically in the src/app/shared folder. 

Read the frontend-components.md for more info.

## Angular Component Development & Testing

For Angular components, you can develop and test them using Storybook + Cypress.

### 1. Create Storybook Stories

Create a `.stories.ts` file alongside your component:

**Location:** `src/app/[feature]/[component-name]/[component-name].component.stories.ts`

**Example structure:**
```typescript
import type { Meta, StoryObj } from '@storybook/angular';
import { ComponentFixture } from '@angular/core/testing';
import { YourComponent } from './your-component.component';

const meta: Meta<YourComponent> = {
  title: 'Example/YourComponent',
  component: YourComponent,
  parameters: {
    layout: 'centered',
  },
  tags: ['autodocs'],
};

export default meta;
type Story = StoryObj<YourComponent>;

export const Default: Story = {
  args: {
    // your component properties
  },
};

export const ErrorState: Story = {
  args: {
    // error scenario properties
  },
};
```

### 2. Run Storybook

**⚠️ Important:** Storybook must be running before you can run Cypress tests.

```bash
cd frontend && npm run storybook
```

Storybook will be available at: `http://localhost:6006`

### 3. Create Cypress Component Tests

**Location:** `cypress/e2e/[component-name]-storybook.cy.ts`

**Example structure:**
```typescript
describe('YourComponent - Storybook Tests', () => {
  
  it('should test default story', () => {
    cy.visit('/iframe.html?id=example-yourcomponent--default')
    
    cy.get('[data-testid="your-component"]').should('be.visible')
    // Add your test assertions
  })

  it('should test error state story', () => {
    cy.visit('/iframe.html?id=example-yourcomponent--error-state')
    
    // Test error state behavior
  })
})
```

### 4. Run Component Tests

**Prerequisites:**
- Storybook must be running (`npm run storybook`)
- Tests access Storybook at `http://localhost:6006`

**Visual Testing (recommended):**
```bash
cd frontend && npm run test:import
```
- Opens Cypress Test Runner GUI
- Click on your test file to watch tests execute
- See real-time component interaction

**Headless Testing:**
```bash
cd frontend && npm run cypress:test:import
```
- Runs tests in background without GUI
- Faster for CI/CD pipelines

**Available npm scripts:**
- `npm run cypress:open` - General Cypress Test Runner
- `npm run test:import` - Visual mode for import component tests
- `npm run cypress:test:import` - Headless mode for import component tests
- `npm run cypress:run` - **Run ALL Cypress tests (headless) - Perfect for GitHub workflows**

### Testing Approach

This approach provides:
- **Component isolation** through Storybook stories
- **Real browser testing** through Cypress e2e against Storybook
- **Visual debugging** capabilities 
- **Multiple test scenarios** per component (success, error, edge cases)

## Unit testing

Jest is installed.

Run: `cd frontend && npm run test` to run the unit tests.

Check the `/coverage` folder for the coverage of your code, and open the `index.html`.

You need to have a Live Server or equivalent to open the coverage report directly from there.

## Stories testing

Run:`cd frontend && npm run storybook` to start your Storybook instance

Run: `npm run test:stories` to run the cypress test on the storybook files. 

This command is also run in the pipeline

## E2E testing
Run: 
`docker compose up -d`: to get your database up

`./generate.sh <arguments>`: to create the instance you would like to test

`cd frontend && npm run cypress:open`: to open the cypress test suite and select test files within the e2e folder. 

Please note that you have to have an Ampersand application running on localhost. 

For that reason, this is not running in the pipeline as setting up docker containers, generate the application etcetera would seriously delay the CI/CD. 

