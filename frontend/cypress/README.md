# Excel Import Testing Setup

This directory contains Cypress end-to-end tests specifically designed to reproduce and test fixes for the Excel import functionality bugs.

## Identified Issues to Test

### 1. **Uninformative 500 Error Messages**
- **Problem**: Generic "500" error messages that don't help users understand what went wrong
- **Expected Fix**: Clear, actionable error messages with guidance

### 2. **400 Error Messages Missing Filename**
- **Problem**: Error messages show location (line/column) but not which file caused the error
- **Expected Fix**: Include filename along with location information

### 3. **Missing Progress Indication**
- **Problem**: Users can't distinguish between "upload in progress" and "system stuck"
- **Expected Fix**: Clear progress bars and status indicators

### 4. **Multiple File Upload Bug**
- **Problem**: Selecting additional files during upload may cause files to be uploaded twice
- **Expected Fix**: Proper queuing or prevention of additional selections during upload

## Running the Tests

### Prerequisites
1. Make sure the backend server is running on `http://localhost`
2. Ensure the Excel import functionality is accessible at `/admin/population/import`

### Commands

```bash
# Run tests in headless mode
npm run cypress:run

# Open Cypress GUI for interactive testing
npm run cypress:open

# Alternative commands
npm run e2e              # Run all e2e tests
npm run e2e:open         # Open Cypress GUI
```

### Storybook Integration

You can also test components in isolation using Storybook:

```bash
# Start Storybook
npm run storybook

# Visit http://localhost:6006
# Navigate to Admin/Population/Import stories
```

## Test Structure

### Custom Commands (`cypress/support/commands.ts`)
- `cy.visitImportPage()` - Navigate to import page
- `cy.uploadFile(fileName, mimeType)` - Upload a file
- `cy.uploadMockExcelFile(fileName, errorType)` - Upload with simulated responses
- `cy.waitForUploadResult()` - Wait for API response

### Test Files
- `excel-import-bugs.cy.ts` - Main test file covering all identified bugs

## Test Scenarios

### Bug Reproduction Tests
These tests reproduce the current buggy behavior:
- Generic 500 errors
- Missing filenames in 400 errors  
- Lack of progress indication
- Multiple file upload issues

### Desired Behavior Tests
These tests define how the functionality SHOULD work:
- Informative error messages
- Progress indicators
- Proper file queuing
- Error recovery options

## Debugging Tips

1. **Network Issues**: Check browser dev tools for failed API calls
2. **Element Selection**: Use Cypress selector playground to verify selectors
3. **Timing Issues**: Add appropriate waits for async operations
4. **API Mocking**: Verify intercepted requests are working correctly

## Contributing

When fixing bugs:
1. Run the "current bug" tests to verify they fail as expected
2. Implement your fix
3. Run the "desired behavior" tests to verify they now pass
4. Update test expectations if needed

## Files Modified for Testing

- Added Storybook stories: `import.component.stories.ts`
- Added Cypress config: `cypress.config.ts`
- Added custom commands: `cypress/support/commands.ts`
- Added test scenarios: `cypress/e2e/excel-import-bugs.cy.ts`
