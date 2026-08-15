# Ampersand Test Project Workflow

## Standard Testing Instructions for All Projects

This document contains the standard workflow for testing new ATOMIC and BOX components. These instructions should be followed consistently across all Cline threads.

## Basic Testing Workflow

### 1. Start Development Environment

```bash
docker compose up -d
```

(don't do this when containers are already running)
This starts the required containers (database etc.)

### 2. Generate and Build the Project

```bash
./generate.sh <test projec><entry file>
```

For example:

```bash
./generate.sh feature-254-filtered-dropdown main.adl
```

This command:

- Compiles Ampersand (.adl) code to backend PHP code
- Generates Angular frontend components from Ampersand interfaces
- Builds Angular code to JavaScript bundles
- Bootstraps localhost server

**Expected:** Command should complete without errors

### 3. Browser Testing

1. Visit http://localhost in browser
2. Click on the **'dropdown'** test in the navigation menu (actually menu items depends on test project)
3. Navigate through all available tabs
4. **Expected:**
   - No visual errors on pages
   - No JavaScript console errors
   - All components render correctly

### 4. Console Verification

- Open browser developer tools
- Check console for any JavaScript errors
- Look for our debug logging messages related to CRUD extraction

## IMPORTANT COMMAND RESTRICTIONS

- **NEVER** run any other docker commands besides `docker compose up -d`
- **NEVER** use `ng serve` or `ng build` directly
- **ONLY** use the `./generate.sh` command to compile and build the project
- The generate.sh script handles all compilation and build processes automatically

## Project Context: feature-254-filtered-dropdown
